<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Seeders;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\Bible\Models\BibleChapter;
use App\Domain\Reference\Data\BibleBookCatalog;
use Database\Seeders\BibleCanonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BibleCanonSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_all_sixty_six_books_with_localized_names_and_chapters(): void
    {
        $this->seed(BibleCanonSeeder::class);

        $this->assertSame(66, BibleBook::query()->count());
        $this->assertSame(
            array_sum(BibleBookCatalog::BOOKS),
            BibleChapter::query()->count(),
        );

        $genesis = BibleBook::query()->where('abbreviation', 'GEN')->firstOrFail();
        $this->assertSame(1, $genesis->position);
        $this->assertSame('old', $genesis->testament);
        $this->assertSame(50, $genesis->chapter_count);
        $this->assertSame('Genesis', $genesis->names['en']);
        $this->assertSame('Geneza', $genesis->names['ro']);

        $revelation = BibleBook::query()->where('abbreviation', 'REV')->firstOrFail();
        $this->assertSame(66, $revelation->position);
        $this->assertSame('new', $revelation->testament);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(BibleCanonSeeder::class);
        $this->seed(BibleCanonSeeder::class);

        $this->assertSame(66, BibleBook::query()->count());
    }
}
