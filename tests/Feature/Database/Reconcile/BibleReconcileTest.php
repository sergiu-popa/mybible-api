<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

final class BibleReconcileTest extends ReconcileTestCase
{
    use RefreshDatabase;

    public function test_it_renames_bible_to_bible_versions(): void
    {
        $this->seedLegacyShape();

        $migration = $this->loadMigration('2026_05_03_000200_reconcile_symfony_bible_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $this->assertTableExists('bible_versions');
        $this->assertTableMissing('bible');

        $this->assertSame('VDC', DB::table('bible_versions')->where('id', 1)->value('abbreviation'));
        $this->assertColumnExists('bible_versions', 'created_at');
        $this->assertColumnExists('bible_versions', 'updated_at');
    }

    public function test_it_dedupes_book_into_bible_books_and_populates_legacy_book_map(): void
    {
        $this->seedLegacyShape();

        DB::table('book')->insert([
            ['id' => 10, 'bible_id' => 1, 'abbreviation' => 'GEN', 'name' => 'Geneza'],
            ['id' => 11, 'bible_id' => 2, 'abbreviation' => 'GEN', 'name' => 'Genesis'],
            ['id' => 12, 'bible_id' => 1, 'abbreviation' => '1CO', 'name' => '1 Corinteni'],
        ]);

        // The book-name backfill needs the legacy abbreviation map seeded.
        $this->loadMigration('2026_05_03_000100_create_legacy_book_abbreviation_map_table.php');

        $migration = $this->loadMigration('2026_05_03_000200_reconcile_symfony_bible_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $this->assertTableExists('bible_books');
        $this->assertTableMissing('book');

        $globalGen = DB::table('bible_books')->where('abbreviation', 'GEN')->first();
        $globalCo = DB::table('bible_books')->where('abbreviation', '1CO')->first();

        $this->assertNotNull($globalGen, 'GEN row should be present in bible_books');
        $this->assertNotNull($globalCo, '1CO row should be present in bible_books');

        $this->assertSame((int) $globalGen->id, (int) DB::table('_legacy_book_map')->where('legacy_book_id', 10)->value('bible_book_id'));
        $this->assertSame((int) $globalGen->id, (int) DB::table('_legacy_book_map')->where('legacy_book_id', 11)->value('bible_book_id'));
        $this->assertSame((int) $globalCo->id, (int) DB::table('_legacy_book_map')->where('legacy_book_id', 12)->value('bible_book_id'));
    }

    public function test_it_renames_verse_to_bible_verses_with_nullable_fk_columns(): void
    {
        $this->seedLegacyShape();

        $migration = $this->loadMigration('2026_05_03_000200_reconcile_symfony_bible_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $this->assertTableExists('bible_verses');
        $this->assertTableMissing('verse');

        $this->assertColumnExists('bible_verses', 'bible_version_id');
        $this->assertColumnExists('bible_verses', 'bible_book_id');
    }

    public function test_it_is_a_no_op_when_legacy_bible_table_is_absent(): void
    {
        // No legacy seeding — fresh CI/dev shape.
        $this->assertTrue(Schema::hasTable('bible_versions'));

        $migration = $this->loadMigration('2026_05_03_000200_reconcile_symfony_bible_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        // Still present, no _legacy_book_map created.
        $this->assertTrue(Schema::hasTable('bible_versions'));
        $this->assertFalse(Schema::hasTable('_legacy_book_map'));
    }

    private function seedLegacyShape(): void
    {
        // Drop the Laravel-shape tables so the rename target is free.
        Schema::dropIfExists('bible_verses');
        Schema::dropIfExists('bible_chapters');
        Schema::dropIfExists('bible_books');
        Schema::dropIfExists('bible_versions');

        Schema::create('bible', function (Blueprint $table): void {
            $table->id();
            $table->string('abbreviation', 16);
            $table->string('name');
            $table->string('language', 8);
            $table->boolean('has_audio')->default(false);
        });

        Schema::create('book', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bible_id');
            $table->string('abbreviation', 16);
            $table->string('name');
        });

        Schema::create('verse', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bible_id');
            $table->unsignedBigInteger('book_id');
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->text('text');
        });

        DB::table('bible')->insert([
            ['id' => 1, 'abbreviation' => 'VDC', 'name' => 'Cornilescu', 'language' => 'ron', 'has_audio' => false],
            ['id' => 2, 'abbreviation' => 'KJV', 'name' => 'King James', 'language' => 'eng', 'has_audio' => false],
        ]);
    }
}
