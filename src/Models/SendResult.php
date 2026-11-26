<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** What sending answers: the messages are queued, not yet sent. */
final readonly class SendResult
{
    /**
     * @param list<int> $messageIds one id per recipient
     * @param list<string> $numbers the numbers queued, in international form
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public array $messageIds, public int $recipients, public int $smsUnits, public string $status,
        public int $remainingUnits, public bool $idempotentReplay, public array $numbers, public array $raw = [],
    ) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(array_map('intval', $d['message_ids'] ?? []), (int)($d['recipients'] ?? 0), (int)($d['sms_units'] ?? 0), (string)($d['status'] ?? ''),
            (int)($d['remaining_units'] ?? 0), (bool)($d['idempotent_replay'] ?? false), array_map('strval', $d['numbers'] ?? []), $d);
    }

    /** The id of the message, when you sent to one number. */
    public function messageId(): int
    {
        return $this->messageIds[0] ?? throw new \LogicException('No message was queued.');
    }
}
