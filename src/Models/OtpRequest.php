<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** A one-time password that was sent. Keep the reference on your server and use it to verify the code. */
final readonly class OtpRequest
{
    /** @param array<string,mixed> $raw */
    public function __construct(public string $requestReference, public int $expiresIn, public string $status, public string $channel, public ?int $emailOtpRemaining, public ?string $emailOtpResetsAt, public array $raw = []) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((string)$d['request_reference'], (int)$d['expires_in'], (string)$d['status'], (string)$d['channel'], isset($d['email_otp_remaining']) ? (int)$d['email_otp_remaining'] : null, $d['email_otp_resets_at'] ?? null, $d);
    }
}
