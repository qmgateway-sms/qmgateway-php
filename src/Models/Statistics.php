<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** Totals for a period, your plan balance and the latest messages. */
final readonly class Statistics
{
    /**
     * @param array<string,int> $overview total, sent, delivered, failed, pending, scheduled, unique_recipients, sms_units
     * @param array<string,mixed> $quota plan, quota, used, reserved, remaining, expires_at
     * @param list<array<string,mixed>> $recentMessages
     * @param list<array<string,mixed>> $daily date, total, sent, failed
     * @param list<array<string,mixed>> $statusDistribution
     * @param list<array<string,mixed>> $topRecipients
     * @param array<string,int> $counts scheduled, templates, contacts, incoming
     * @param array<string,mixed> $period start_date, end_date, type
     * @param array<string,mixed> $raw
     */
    public function __construct(public array $overview, public array $quota, public array $recentMessages, public array $daily, public array $statusDistribution, public array $topRecipients, public array $counts, public array $period, public array $raw = []) {}

    /** @param array<string,mixed> $d the whole answer (statistics is not wrapped in "data") */
    public static function fromArray(array $d): self
    {
        $s = $d['statistics'] ?? [];
        return new self($s['overview'] ?? [], $s['quota'] ?? [], $s['recent_messages'] ?? [], $s['daily'] ?? [], $s['status_distribution'] ?? [], $s['top_recipients'] ?? [], $s['counts'] ?? [], $d['period'] ?? [], $d);
    }
}
