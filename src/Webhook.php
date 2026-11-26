<?php
declare(strict_types=1);

namespace QMGateway;

use QMGateway\Exception\SignatureException;

/**
 * Checks that a webhook really came from QMGateway. Use the raw request body, exactly as received (not re-encoded JSON).
 *
 *     $event = QMGateway\Webhook::verify(file_get_contents('php://input'), getallheaders(), $secret);
 */
final class Webhook
{
    public const TOLERANCE = 300;

    /**
     * @param array<string,string> $headers any case: "x-qmgateway-signature" and "X-QMGateway-Signature" both work
     * @param int|null $now unix time to compare with (tests pass a fixed one)
     * @throws SignatureException when the request is not genuine
     */
    public static function verify(string $body, array $headers, string $secret, int $tolerance = self::TOLERANCE, ?int $now = null): WebhookEvent
    {
        $h = array_change_key_case($headers, CASE_LOWER);
        $signature = $h['x-qmgateway-signature'] ?? '';
        $timestamp = $h['x-qmgateway-timestamp'] ?? '';
        if ($signature === '' || $timestamp === '') throw new SignatureException('missing_header');
        if (!ctype_digit($timestamp) || abs(($now ?? time()) - (int)$timestamp) > $tolerance) throw new SignatureException('stale_timestamp');
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        if (!hash_equals($expected, $signature)) throw new SignatureException('bad_signature');

        $data = json_decode($body, true);
        if (!is_array($data)) throw new SignatureException('bad_signature');
        return new WebhookEvent((string)($h['x-qmgateway-event'] ?? ''), (string)($h['x-qmgateway-event-id'] ?? ''), (int)($h['x-qmgateway-attempt'] ?? 1), $data);
    }

    /** Makes a correctly signed request, for testing your own webhook handler. @return array{body:string,headers:array<string,string>} */
    public static function sign(string $body, string $secret, string $event = 'sms.delivered', ?int $timestamp = null, string $eventId = 'evt_test'): array
    {
        $t = (string)($timestamp ?? time());
        return ['body' => $body, 'headers' => [
            'X-QMGateway-Timestamp' => $t, 'X-QMGateway-Signature' => 'sha256=' . hash_hmac('sha256', $t . '.' . $body, $secret),
            'X-QMGateway-Event' => $event, 'X-QMGateway-Event-ID' => $eventId, 'X-QMGateway-Attempt' => '1',
        ]];
    }
}
