<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Runner;

use ArrayAccess;
use IteratorAggregate;
use Traversable;

/**
 * @template TKey
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 *
 * @codeCoverageIgnore
 */
final class RunnerContext implements ArrayAccess, IteratorAggregate
{
    public RunnerState $state;

    /**
     * @param array<TKey, TValue> $data
     */
    public function __construct(
        private array $data = [],
    ) {
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]); // @phpstan-ignore offsetAccess.invalidOffset
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null; // @phpstan-ignore offsetAccess.invalidOffset
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value; // @phpstan-ignore offsetAccess.invalidOffset, assign.propertyType
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]); // @phpstan-ignore offsetAccess.invalidOffset
    }

    public function getIterator(): Traversable
    {
        yield from $this->data;
    }
}
