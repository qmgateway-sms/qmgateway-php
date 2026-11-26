<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** One service of your account (today: sms) and whether it can be used. */
final readonly class Service
{
    /** @param array<string,mixed> $details for sms: plan, remaining_sms, phones, phones_online, receives_replies, ready_to_send */
    public function __construct(public string $key, public string $name, public string $status, public array $details) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((string)$d['key'], (string)$d['name'], (string)$d['status'], is_array($d['details'] ?? null) ? $d['details'] : []);
    }

    /** True when a message could go out right now (plan active, SMS left, a phone online). */
    public function readyToSend(): bool { return (bool)($this->details['ready_to_send'] ?? false); }
}
