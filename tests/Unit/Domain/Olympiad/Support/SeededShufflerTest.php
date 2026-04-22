<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Olympiad\Support;

use App\Domain\Olympiad\Support\SeededShuffler;
use PHPUnit\Framework\TestCase;

final class SeededShufflerTest extends TestCase
{
    private SeededShuffler $shuffler;

    protected function setUp(): void
    {
        $this->shuffler = new SeededShuffler;
    }

    public function test_same_seed_produces_same_order(): void
    {
        $items = range(1, 10);

        $first = $this->shuffler->shuffle($items, 1234);
        $second = $this->shuffler->shuffle($items, 1234);

        $this->assertSame($first, $second);
    }

    public function test_different_seeds_produce_different_orders(): void
    {
        $items = range(1, 10);

        $first = $this->shuffler->shuffle($items, 1);
        $second = $this->shuffler->shuffle($items, 999_999);

        $this->assertNotSame($first, $second);
        $this->assertEqualsCanonicalizing($items, $first);
        $this->assertEqualsCanonicalizing($items, $second);
    }

    public function test_empty_array_yields_empty_array(): void
    {
        $this->assertSame([], $this->shuffler->shuffle([], 42));
    }

    public function test_returns_zero_indexed_list(): void
    {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];

        $result = $this->shuffler->shuffle($items, 7);

        $this->assertSame([0, 1, 2], array_keys($result));
        $this->assertEqualsCanonicalizing([1, 2, 3], $result);
    }
}
