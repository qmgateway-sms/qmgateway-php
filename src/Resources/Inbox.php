<?php
declare(strict_types=1);

namespace QMGateway\Resources;

use QMGateway\Models\{InboxMessage, Page};

/** Replies received on your gateway phones, oldest first. */
final class Inbox extends Resource
{
    public function list(?int $afterId = null, ?string $from = null, ?int $limit = null): Page
    {
        $a = $this->http->request('GET', '/api/v1/inbox', ['after_id' => $afterId, 'from' => $from, 'limit' => $limit]);
        return new Page(array_map(InboxMessage::fromArray(...), $a['data'] ?? []), isset($a['next_after_id']) ? (int)$a['next_after_id'] : null);
    }

    /** @return \Generator<int,InboxMessage> */
    public function each(?string $from = null, ?int $afterId = null, int $pageSize = 100): \Generator
    {
        $cursor = $afterId;
        do {
            $page = $this->list($cursor, $from, $pageSize);
            yield from $page->items;
            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }
}
