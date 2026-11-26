<?php
declare(strict_types=1);

namespace QMGateway;

use QMGateway\Exception\ConnectionException;

/** The default transport: PHP's own cURL extension. Certificates are always verified and redirects are never followed. */
final class CurlTransport implements Transport
{
    public function __construct(private readonly ?string $caInfo = null) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $headers = [];
        foreach ($request->headers as $name => $value) $headers[] = $name . ': ' . $value;

        $responseHeaders = [];
        $ch = curl_init($request->url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,           // a redirect could carry the API key to another host
            CURLOPT_TIMEOUT_MS => (int)round($request->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int)round(min($request->timeout, 10.0) * 1000),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_MAXFILESIZE => 8 * 1024 * 1024,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $pos = strpos($line, ':');
                if ($pos !== false) $responseHeaders[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
                return strlen($line);
            },
        ];
        if ($request->body !== null) $options[CURLOPT_POSTFIELDS] = $request->body;
        if ($this->caInfo !== null) $options[CURLOPT_CAINFO] = $this->caInfo;
        elseif (!ini_get('curl.cainfo') && !ini_get('openssl.cafile') && defined('CURLSSLOPT_NATIVE_CA')) $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;   // Windows: use the system certificate store
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        if ($body === false) {
            $message = curl_error($ch); $errno = curl_errno($ch);
            throw new ConnectionException(self::describe($errno, $message), $errno);
        }
        return new HttpResponse((int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $responseHeaders, (string)$body);
    }

    private static function describe(int $errno, string $message): string
    {
        return match (true) {
            $errno === CURLE_OPERATION_TIMEDOUT => 'The request timed out.',
            $errno === CURLE_COULDNT_RESOLVE_HOST => 'The address could not be found (DNS).',
            $errno === CURLE_COULDNT_CONNECT => 'Could not connect to QMGateway.',
            in_array($errno, [CURLE_SSL_CONNECT_ERROR, CURLE_PEER_FAILED_VERIFICATION, CURLE_SSL_CACERT_BADFILE], true) => 'The secure (HTTPS) connection failed: ' . $message,
            default => 'The request failed: ' . ($message !== '' ? $message : 'no answer'),
        };
    }
}
