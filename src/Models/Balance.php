<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** What is left of your plan. */
final readonly class Balance
{
    /** @param array<string,mixed> $raw */
    public function __construct(
        public bool $active, public ?string $plan, public int $quota, public int $used, public int $reserved, public int $remaining,
        public int $percentLeft, public string $level, public ?string $startsAt, public ?string $expiresAt, public ?int $daysLeft, public ?EmailOtpBalance $emailOtp, public array $raw = [],
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((bool)$d['active'], $d['plan'] ?? null, (int)$d['quota'], (int)$d['used'], (int)$d['reserved'], (int)$d['remaining'], (int)$d['percent_left'], (string)$d['level'],
            $d['starts_at'] ?? null, $d['expires_at'] ?? null, isset($d['days_left']) ? (int)$d['days_left'] : null, isset($d['email_otp']) ? EmailOtpBalance::fromArray($d['email_otp']) : null, $d);
    }

    public function isLow(): bool { return in_array($this->level, ['low', 'critical', 'empty'], true); }
}
