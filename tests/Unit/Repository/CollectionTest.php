<?php

declare(strict_types=1);

namespace Spiral\RoadRunner\Console\Tests\Unit\Repository;

use Spiral\RoadRunner\Console\Repository\AssetInterface;
use Spiral\RoadRunner\Console\Repository\AssetsCollection;
use Spiral\RoadRunner\Console\Tests\Unit\Stub\Releases;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

/**
 * Behaviour shared by all collections, exercised through {@see AssetsCollection}.
 */
#[Test]
final class CollectionTest
{
    public function createReturnsSameInstance(): void
    {
        $collection = new AssetsCollection([]);

        Assert::same(AssetsCollection::create($collection), $collection);
    }

    public function createAcceptsArray(): void
    {
        $collection = AssetsCollection::create([Releases::asset('a'), Releases::asset('b')]);

        Assert::same(self::names($collection), ['a', 'b']);
    }

    public function createAcceptsTraversable(): void
    {
        $collection = AssetsCollection::create(new \ArrayIterator([Releases::asset('a')]));

        Assert::same(self::names($collection), ['a']);
    }

    public function createAcceptsGeneratorClosure(): void
    {
        $collection = AssetsCollection::create(static function (): \Generator {
            yield Releases::asset('a');
            yield Releases::asset('b');
        });

        Assert::same(self::names($collection), ['a', 'b']);
    }

    public function createRejectsUnsupportedValue(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Unsupported iterable type string');

        AssetsCollection::create('assets');
    }

    public function filterAndExceptAreComplementary(): void
    {
        $collection = self::abc();
        $isB = static fn(AssetInterface $asset): bool => $asset->getName() === 'b';

        Assert::same(self::names($collection->filter($isB)), ['b']);
        Assert::same(self::names($collection->except($isB)), ['a', 'c']);
    }

    public function mapTransformsItems(): void
    {
        $mapped = self::abc()->map(
            static fn(AssetInterface $asset): AssetInterface => Releases::asset(\strtoupper($asset->getName())),
        );

        Assert::same(self::names($mapped), ['A', 'B', 'C']);
    }

    public function firstReturnsFirstMatchingItem(): void
    {
        $collection = self::abc();
        $notA = static fn(AssetInterface $asset): bool => $asset->getName() !== 'a';

        Assert::same($collection->first()?->getName(), 'a');
        Assert::same($collection->first($notA)?->getName(), 'b');
        Assert::null($collection->first(static fn(): bool => false));
        Assert::null((new AssetsCollection([]))->first());
    }

    public function firstOrFallsBackToOtherwise(): void
    {
        $fallback = Releases::asset('fallback');

        Assert::same(self::abc()->firstOr(static fn(): AssetInterface => $fallback)->getName(), 'a');
        Assert::same((new AssetsCollection([]))->firstOr(static fn(): AssetInterface => $fallback), $fallback);
    }

    public function whenEmptyCallsBackOnlyForEmptyCollection(): void
    {
        $calls = 0;
        $callback = static function () use (&$calls): void {
            ++$calls;
        };
        $collection = self::abc();
        $empty = new AssetsCollection([]);

        Assert::same($collection->whenEmpty($callback), $collection);
        Assert::same($calls, 0);
        Assert::same($empty->whenEmpty($callback), $empty);
        Assert::same($calls, 1);
    }

    public function countEmptyAndToArray(): void
    {
        $filtered = self::abc()->filter(static fn(AssetInterface $asset): bool => $asset->getName() !== 'a');

        Assert::count($filtered, 2);
        Assert::false($filtered->empty());
        Assert::true((new AssetsCollection([]))->empty());
        Assert::array($filtered->toArray())->isList()->hasCount(2);
    }

    private static function abc(): AssetsCollection
    {
        return new AssetsCollection([Releases::asset('a'), Releases::asset('b'), Releases::asset('c')]);
    }

    /**
     * @return list<string>
     */
    private static function names(AssetsCollection $collection): array
    {
        return \array_map(static fn(AssetInterface $asset): string => $asset->getName(), $collection->toArray());
    }
}
