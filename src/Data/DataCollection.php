<?php

namespace InnovativeSolutions\TMetric\Data;

use ArrayIterator;
use Countable;
use InnovativeSolutions\TMetric\Exceptions\SchemaDriftException;
use IteratorAggregate;
use Traversable;

/**
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class DataCollection implements Countable, IteratorAggregate
{
    /** @param list<T> $items */
    public function __construct(private array $items) {}

    /** @return list<T> */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @template V
     *
     * @param  array<mixed>  $rows
     * @param  callable(array<string, mixed>): V  $factory
     * @return self<V>
     */
    public static function fromRows(array $rows, callable $factory): self
    {
        $items = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new SchemaDriftException(
                    'TMetric collection contains a non-object item.',
                );
            }

            $items[] = $factory($row);
        }

        return new self($items);
    }
}
