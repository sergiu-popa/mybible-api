<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Migration\Actions;

use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use App\Domain\Migration\Exceptions\UnmappedLegacyBookException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class BackfillLegacyBookAbbreviationsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('book_backfill_fixture', function (Blueprint $table): void {
            $table->id();
            $table->string('book', 64);
        });

        // `_legacy_book_abbreviation_map` is created and seeded by
        // `2026_05_03_000100_create_legacy_book_abbreviation_map_table.php`
        // — RefreshDatabase has already populated it with the Romanian +
        // English long-form names → USFM-3 mapping. No fixture setup needed.
    }

    public function test_it_rewrites_long_form_names_to_usfm_three(): void
    {
        $id = DB::table('book_backfill_fixture')->insertGetId(['book' => 'Genesis']);

        (new BackfillLegacyBookAbbreviationsAction)->handle('book_backfill_fixture', 'book');

        $this->assertSame('GEN', DB::table('book_backfill_fixture')->where('id', $id)->value('book'));
    }

    public function test_it_passes_through_already_canonical_values(): void
    {
        $id = DB::table('book_backfill_fixture')->insertGetId(['book' => 'GEN']);

        (new BackfillLegacyBookAbbreviationsAction)->handle('book_backfill_fixture', 'book');

        $this->assertSame('GEN', DB::table('book_backfill_fixture')->where('id', $id)->value('book'));
    }

    public function test_it_throws_loudly_when_value_is_not_in_the_map(): void
    {
        DB::table('book_backfill_fixture')->insert(['book' => 'Mystery Book']);

        $this->expectException(UnmappedLegacyBookException::class);

        (new BackfillLegacyBookAbbreviationsAction)->handle('book_backfill_fixture', 'book');
    }
}
