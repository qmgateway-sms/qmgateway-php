<?php
declare(strict_types=1);

namespace QMGateway\Models;

/**
 * One page of a list, and where the next one starts.
 * @template T
 * @implements \IteratorAggregate<int,T>
 */
final readonly class Page implements \IteratorAggregate, \Countable
{
    /** @param list<T> $items */
    public function __construct(public array $items, public ?int $nextCursor) {}

    public function hasMore(): bool { return $this->nextCursor !== null; }
    public function count(): int { return count($this->items); }
    public function getIterator(): \ArrayIterator { return new \ArrayIterator($this->items); }
}
