<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** One message: queued, sent, delivered or failed. */
final readonly class Message
{
    /** @param array<string,mixed> $raw everything the API sent, including fields newer than this SDK */
    public function __construct(
        public int $id,
        public string $phoneNumber,
        public string $status,
        public string $messageType,
        public int $smsUnits,
        public ?\DateTimeImmutable $scheduledTime,
        public ?\DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $sentAt,
        public ?\DateTimeImmutable $deliveredAt,
        public ?\DateTimeImmutable $failedAt,
        public ?string $failureReason,
        public ?string $mode,
        public array $raw = [],
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((int)$d['id'], (string)$d['phone_number'], (string)$d['status'], (string)($d['message_type'] ?? ''), (int)($d['sms_units'] ?? 1),
            Time::parse($d['scheduled_time'] ?? null), Time::parse($d['created_at'] ?? null), Time::parse($d['sent_at'] ?? null), Time::parse($d['delivered_at'] ?? null),
            Time::parse($d['failed_at'] ?? null), $d['failure_reason'] ?? null, $d['mode'] ?? null, $d);
    }

    public function isDelivered(): bool { return $this->status === 'Delivered'; }
    public function isFailed(): bool { return $this->status === 'Failed'; }
    /** Delivered, failed or cancelled: nothing more will happen to it. */
    public function isFinished(): bool { return in_array($this->status, ['Delivered', 'Failed', 'Cancelled'], true); }
}
