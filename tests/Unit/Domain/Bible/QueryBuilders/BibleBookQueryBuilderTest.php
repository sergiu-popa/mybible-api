<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bible\QueryBuilders;

use App\Domain\Bible\Models\BibleBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BibleBookQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_canonical_order_orders_by_position(): void
    {
        BibleBook::factory()->create(['position' => 3, 'abbreviation' => 'AAA']);
        BibleBook::factory()->create(['position' => 1, 'abbreviation' => 'BBB']);
        BibleBook::factory()->create(['position' => 2, 'abbreviation' => 'CCC']);

        $positions = BibleBook::query()->inCanonicalOrder()->pluck('position')->all();

        $this->assertSame([1, 2, 3], $positions);
    }
}
