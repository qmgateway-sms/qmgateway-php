<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** A message waiting for its send time. */
final readonly class ScheduledMessage
{
    /** @param array<string,mixed> $raw */
    public function __construct(public int $id, public string $phoneNumber, public string $message, public int $smsUnits, public ?\DateTimeImmutable $scheduledAt, public ?\DateTimeImmutable $createdAt, public array $raw = []) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((int)$d['id'], (string)$d['phone_number'], (string)($d['message'] ?? ''), (int)($d['sms_units'] ?? 1), Time::parse($d['scheduled_at'] ?? null), Time::parse($d['created_at'] ?? null), $d);
    }
}
