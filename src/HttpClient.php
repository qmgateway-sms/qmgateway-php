<?php
declare(strict_types=1);

namespace QMGateway;

use QMGateway\Exception\ApiException;
use QMGateway\Exception\AuthenticationException;
use QMGateway\Exception\ConflictException;
use QMGateway\Exception\ConnectionException;
use QMGateway\Exception\InvalidRequestException;
use QMGateway\Exception\NotFoundException;
use QMGateway\Exception\PermissionException;
use QMGateway\Exception\QMGatewayException;
use QMGateway\Exception\RateLimitException;
use QMGateway\Exception\ServerException;

/**
 * Sends requests and does the part every call needs: the key, the retries, and turning an error answer into the right exception.
 *
 * Retry rules (the shared QMGateway SDK contract):
 *  - network errors, 429 and 5xx are retried, other 4xx never;
 *  - wait Retry-After seconds when the answer has it (never more than 60), otherwise 1, 2, 4, 8 seconds, plus up to 20% jitter;
 *  - up to Options::$maxRetries retries (4 by default);
 *  - a request that is not safe to repeat (no Idempotency-Key) is retried only on 429, because then it was surely not processed.
 */
final class HttpClient
{
    private const MAX_WAIT = 60.0;

    public function __construct(private readonly string $apiKey, private readonly Options $options)
    {
        if ($apiKey === '' || preg_match('/[\s\r\n]/', $apiKey)) throw new \InvalidArgumentException('The API key is missing or has spaces in it.');
    }

    public function options(): Options
    {
        return $this->options;
    }

    /**
     * @param array<string,scalar|null> $query
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     * @return array<string,mixed> the decoded answer
     * @throws QMGatewayException
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, array $headers = [], bool $safeToRepeat = true): array
    {
        $query = array_filter($query, static fn($v) => $v !== null);
        $url = $this->options->root() . $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
        $all = ['X-API-Key' => $this->apiKey, 'Accept' => 'application/json', 'User-Agent' => $this->userAgent()] + $headers;
        if ($body !== null) $all['Content-Type'] = 'application/json';
        $request = new HttpRequest($method, $url, $all, $body !== null ? json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null, $this->options->timeout, $safeToRepeat);

        $transport = $this->options->transport ?? new CurlTransport($this->options->caInfo);
        for ($attempt = 0; ; $attempt++) {
            $last = $attempt >= $this->options->maxRetries;
            try {
                $response = $transport->send($request);
            } catch (ConnectionException $e) {
                if ($last || !$request->retryable) throw $e;
                $this->pause($this->backoff($attempt));
                continue;
            }
            $retryable = $response->status === 429 || ($response->status >= 500 && $request->retryable);
            if ($retryable && !$last) {
                $this->pause($this->waitFor($response, $attempt));
                continue;
            }
            return $this->decode($response);
        }
    }

    /** @return array<string,mixed> */
    private function decode(HttpResponse $response): array
    {
        $data = json_decode($response->body, true);
        if ($response->status >= 200 && $response->status < 300 && is_array($data) && ($data['success'] ?? false) === true) return $data;
        throw $this->error($response, is_array($data) ? $data : null);
    }

    /** @param array<string,mixed>|null $data */
    private function error(HttpResponse $response, ?array $data): ApiException
    {
        $e = is_array($data['error'] ?? null) ? $data['error'] : [];
        $status = $response->status;
        $code = is_string($e['code'] ?? null) ? $e['code'] : ($status >= 500 ? 'SERVER_ERROR' : 'INVALID_RESPONSE');
        $message = is_string($e['message'] ?? null) ? $e['message'] : ($status >= 500 ? 'QMGateway had a problem. Please try again shortly.' : 'QMGateway sent an answer this SDK cannot read.');
        $id = is_string($e['request_id'] ?? null) ? $e['request_id'] : $response->header('x-request-id');
        $details = is_array($e['details'] ?? null) ? $e['details'] : [];
        return match (true) {
            $status === 401 => new AuthenticationException($message, $status, $code, $id, $details),
            $status === 403 => new PermissionException($message, $status, $code, $id, $details),
            $status === 404 => new NotFoundException($message, $status, $code, $id, $details),
            $status === 409 => new ConflictException($message, $status, $code, $id, $details),
            $status === 429 => new RateLimitException($message, $status, $code, $id, $details, ctype_digit((string)$response->header('retry-after')) ? (int)$response->header('retry-after') : null),
            $status >= 500 => new ServerException($message, $status, $code, $id, $details),
            $status >= 400 => new InvalidRequestException($message, $status, $code, $id, $details),
            default => new ApiException($message, $status, $code, $id, $details),
        };
    }

    private function waitFor(HttpResponse $response, int $attempt): float
    {
        $after = $response->header('retry-after');
        if ($after !== null && ctype_digit($after)) return min((float)$after, self::MAX_WAIT);
        return $this->backoff($attempt);
    }

    /** 1, 2, 4, 8 ... seconds (at most 60) with up to 20% random jitter so many clients do not retry together. */
    private function backoff(int $attempt): float
    {
        $base = min(2 ** $attempt, self::MAX_WAIT);
        return $base * (1 + random_int(0, 200) / 1000);
    }

    private function pause(float $seconds): void
    {
        $sleeper = $this->options->sleeper;
        if ($sleeper !== null) { $sleeper($seconds); return; }
        usleep((int)round($seconds * 1_000_000));
    }

    private function userAgent(): string
    {
        return trim('qmgateway-php/' . Client::VERSION . ' php/' . PHP_VERSION . ' ' . ($this->options->userAgent ?? ''));
    }
}
