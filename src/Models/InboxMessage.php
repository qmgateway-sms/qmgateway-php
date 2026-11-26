<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** A reply (or any text) received on one of your gateway phones. */
final readonly class InboxMessage
{
    /** @param array<string,mixed> $raw */
    public function __construct(public int $id, public string $from, public string $message, public ?\DateTimeImmutable $receivedAt, public array $raw = []) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((int)$d['id'], (string)$d['from'], (string)$d['message'], Time::parse($d['received_at'] ?? null), $d);
    }
}
