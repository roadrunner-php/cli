<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Repository;

/**
 * @internal Collection is an internal library class, please do not use it in your code.
 * @psalm-internal Spiral\RoadRunner\Console
 *
 * @template T
 *
 * @template-implements \IteratorAggregate<array-key, T>
 */
abstract class Collection implements \IteratorAggregate, \Countable
{
    /**
     * @param array<T> $items
     */
    final public function __construct(
        protected array $items,
    ) {
    }

    /**
     * @param mixed|iterable|\Closure $items
     * @return static
     */
    public static function create($items): self
    {
        switch (true) {
            case $items instanceof static:
                return $items;

            case $items instanceof \Traversable:
                $items = \iterator_to_array($items);

            case \is_array($items):
                return new static($items);

            case $items instanceof \Closure:
                return static::from($items);

            default:
                throw new \InvalidArgumentException(
                    \sprintf('Unsupported iterable type %s', \get_debug_type($items))
                );
        }
    }

    /**
     * @return static
     */
    public static function from(\Closure $generator): self
    {
        return static::create($generator());
    }

    /**
     * @param callable(T): bool $filter
     * @return $this
     */
    public function filter(callable $filter): self
    {
        return new static(\array_filter($this->items, $filter));
    }

    /**
     * @param callable(T): mixed $map
     * @return $this
     */
    public function map(callable $map): self
    {
        return new static(\array_map($map, $this->items));
    }

    /**
     * @param callable(T): bool $filter
     * @return $this
     *
     * @psalm-suppress MissingClosureParamType
     * @psalm-suppress MixedArgument
     */
    public function except(callable $filter): self
    {
        $callback = static fn (...$args): bool => ! $filter(...$args);

        return new static(\array_filter($this->items, $callback));
    }

    /**
     * @param null|callable(T): bool $filter
     * @return T|null
     */
    public function first(callable $filter = null): ?object
    {
        $self = $filter === null ? $this : $this->filter($filter);

        return $self->items === [] ? null : \reset($self->items);
    }

    /**
     * @param callable(): T $otherwise
     * @param null|callable(T): bool $filter
     * @return T
     */
    public function firstOr(callable $otherwise, callable $filter = null): object
    {
        return $this->first($filter) ?? $otherwise();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * @return $this
     */
    public function whenEmpty(callable $then): self
    {
        if ($this->empty()) {
            $then();
        }

        return $this;
    }

    public function empty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return array<T>
     */
    public function toArray(): array
    {
        return \array_values($this->items);
    }
}
