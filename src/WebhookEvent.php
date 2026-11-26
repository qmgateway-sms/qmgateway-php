<?php
declare(strict_types=1);

namespace QMGateway;

use QMGateway\Models\Time;

/** A verified webhook delivery. Events: sms.incoming, sms.sent, sms.delivered, sms.failed, webhook.test. */
final readonly class WebhookEvent
{
    /** @param array<string,mixed> $data the event body as sent */
    public function __construct(public string $type, public string $id, public int $attempt, public array $data) {}

    public function messageId(): ?int { return isset($this->data['message_id']) ? (int)$this->data['message_id'] : null; }
    public function phoneNumber(): ?string { return isset($this->data['phone_number']) ? (string)$this->data['phone_number'] : null; }
    public function status(): ?string { return isset($this->data['status']) ? (string)$this->data['status'] : null; }
    public function isTest(): bool { return $this->type === 'webhook.test'; }
    public function updatedAt(): ?\DateTimeImmutable { return Time::parse($this->data['updated_at'] ?? null); }
}
