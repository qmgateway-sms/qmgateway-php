<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** A gateway phone. */
final readonly class Device
{
    /**
     * @param list<string> $networks
     * @param array<string,mixed> $raw
     */
    public function __construct(public int $id, public string $name, public string $status, public ?\DateTimeImmutable $lastSeenAt, public array $networks, public int $capacity, public int $queued, public array $raw = []) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((int)$d['id'], (string)$d['name'], (string)$d['status'], Time::parse($d['last_seen_at'] ?? null), array_map('strval', $d['networks'] ?? []), (int)($d['capacity'] ?? 1), (int)($d['queued'] ?? 0), $d);
    }

    public function isOnline(): bool { return $this->status === 'online'; }
}
