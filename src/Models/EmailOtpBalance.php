<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** The monthly allowance of email one-time passwords. */
final readonly class EmailOtpBalance
{
    public function __construct(public bool $included, public int $quota, public int $used, public int $remaining, public ?string $periodStart, public ?string $resetsAt) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((bool)($d['included'] ?? false), (int)($d['quota'] ?? 0), (int)($d['used'] ?? 0), (int)($d['remaining'] ?? 0), $d['period_start'] ?? null, $d['resets_at'] ?? null);
    }
}
