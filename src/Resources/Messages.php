<?php
declare(strict_types=1);

namespace QMGateway\Resources;

use QMGateway\Models\{Message, Page, ScheduledMessage, SendResult};

/** Send, track, list, cancel and reschedule messages. */
final class Messages extends Resource
{
    /**
     * Queue a message for one number or a list of numbers.
     *
     * An Idempotency-Key is added for you, so a retry after a timeout can never send twice. To make your own retries safe too
     * (for example a job that runs again), pass the same $idempotencyKey each time.
     *
     * @param string|list<string> $to
     * @param string|\DateTimeInterface|null $scheduledAt Malawi time when given as text ("2026-12-01 08:00:00")
     */
    #[\NoDiscard('Keep the result: it holds the message ids you need to follow the delivery.')]
    public function send(string|array $to, string $message, ?string $type = null, ?int $deviceId = null, string|\DateTimeInterface|null $scheduledAt = null, ?string $idempotencyKey = null): SendResult
    {
        $body = (is_array($to) ? ['recipients' => array_values($to)] : ['phone' => $to]) + ['message' => $message];
        if ($type !== null) $body['type'] = $type;
        if ($deviceId !== null) $body['device_id'] = $deviceId;
        if ($scheduledAt !== null) $body['scheduled_at'] = $scheduledAt instanceof \DateTimeInterface ? $scheduledAt->setTimezone(new \DateTimeZone('Africa/Blantyre'))->format('Y-m-d H:i:s') : $scheduledAt;
        if ($idempotencyKey !== null && !preg_match('/^[!-~]{1,255}$/D', $idempotencyKey)) throw new \InvalidArgumentException('idempotencyKey must be 1 to 255 visible characters with no spaces.');
        $key = $idempotencyKey ?? bin2hex(random_bytes(16));
        return SendResult::fromArray($this->data($this->http->request('POST', '/api/v1/messages', body: $body, headers: ['Idempotency-Key' => $key])));
    }

    /** Same as send() for a list of numbers. @param list<string> $numbers */
    #[\NoDiscard]
    public function sendMany(array $numbers, string $message, ?string $type = null, ?int $deviceId = null, string|\DateTimeInterface|null $scheduledAt = null, ?string $idempotencyKey = null): SendResult
    {
        return $this->send($numbers, $message, $type, $deviceId, $scheduledAt, $idempotencyKey);
    }

    /** One message and where it is now. */
    public function get(int $id): Message
    {
        return Message::fromArray($this->data($this->http->request('GET', '/api/v1/message_status', ['id' => $id])));
    }

    /** One page of messages, newest first. Pass $page->nextCursor as $beforeId for the next page. */
    public function list(?string $status = null, ?string $phone = null, ?string $since = null, ?int $limit = null, ?int $beforeId = null): Page
    {
        $a = $this->http->request('GET', '/api/v1/messages', ['status' => $status, 'phone' => $phone, 'since' => $since, 'limit' => $limit, 'before_id' => $beforeId]);
        return new Page(array_map(Message::fromArray(...), $a['data'] ?? []), isset($a['next_before_id']) ? (int)$a['next_before_id'] : null);
    }

    /** Every matching message, newest first. Fetches the next page when you reach the end of the one before. @return \Generator<int,Message> */
    public function each(?string $status = null, ?string $phone = null, ?string $since = null, int $pageSize = 100): \Generator
    {
        $cursor = null;
        do {
            $page = $this->list($status, $phone, $since, $pageSize, $cursor);
            yield from $page->items;
            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }

    /** One page of messages waiting for their send time. */
    public function scheduled(?int $limit = null, ?int $afterId = null): Page
    {
        $a = $this->http->request('GET', '/api/v1/scheduled', ['limit' => $limit, 'after_id' => $afterId]);
        return new Page(array_map(ScheduledMessage::fromArray(...), $a['data'] ?? []), isset($a['next_after_id']) ? (int)$a['next_after_id'] : null);
    }

    /** @return \Generator<int,ScheduledMessage> */
    public function eachScheduled(int $pageSize = 100): \Generator
    {
        $cursor = null;
        do {
            $page = $this->scheduled($pageSize, $cursor);
            yield from $page->items;
            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }

    /** Cancel a scheduled message. Its SMS units go back to your balance. @return array{id:int,status:string,remaining_units:int} */
    public function cancel(int $id): array
    {
        return $this->data($this->http->request('POST', '/api/v1/message_cancel', body: ['id' => $id]));
    }

    /** Move a scheduled message to a new time. @return array{id:int,status:string,scheduled_at:string} */
    public function reschedule(int $id, string|\DateTimeInterface $scheduledAt): array
    {
        $at = $scheduledAt instanceof \DateTimeInterface ? $scheduledAt->setTimezone(new \DateTimeZone('Africa/Blantyre'))->format('Y-m-d H:i:s') : $scheduledAt;
        return $this->data($this->http->request('POST', '/api/v1/message_reschedule', body: ['id' => $id, 'scheduled_at' => $at]));
    }
}
