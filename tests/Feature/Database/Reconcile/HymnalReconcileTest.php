<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

final class HymnalReconcileTest extends ReconcileTestCase
{
    use RefreshDatabase;

    public function test_it_renames_hymnal_tables_and_adds_song_unique(): void
    {
        $this->dropIfExists('hymnal_favorites');
        $this->dropIfExists('hymnal_songs');
        $this->dropIfExists('hymnal_books');

        $this->recreateLegacyTable('hymnal_book', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->recreateLegacyTable('hymnal_song', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('hymnal_book_id');
            $table->unsignedSmallInteger('number');
            $table->string('title');
            $table->timestamps();
        });

        $this->recreateLegacyTable('hymnal_verse', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('hymnal_song_id');
            $table->text('text');
        });

        DB::table('hymnal_book')->insert(['id' => 1, 'name' => 'Imnuri', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('hymnal_song')->insert([
            'hymnal_book_id' => 1, 'number' => 12, 'title' => 'Song A', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = $this->loadMigration('2026_05_03_000204_reconcile_symfony_hymnal_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $this->assertTableExists('hymnal_books');
        $this->assertTableExists('hymnal_songs');
        $this->assertTableExists('hymnal_verses');
        $this->assertTableMissing('hymnal_book');
        $this->assertTableMissing('hymnal_song');
        $this->assertTableMissing('hymnal_verse');

        $this->assertSame('Imnuri', DB::table('hymnal_books')->where('id', 1)->value('name'));

        DB::table('hymnal_songs')->insert([
            'hymnal_book_id' => 1, 'number' => 13, 'title' => 'Song B', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('hymnal_songs')->insert([
            'hymnal_book_id' => 1, 'number' => 12, 'title' => 'Duplicate', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_song_unique_lands_even_when_legacy_tables_already_renamed(): void
    {
        $this->assertTrue(Schema::hasTable('hymnal_songs'));

        $migration = $this->loadMigration('2026_05_03_000204_reconcile_symfony_hymnal_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        // The migration should add the UNIQUE if it isn't already there.
        $book = DB::table('hymnal_books')->insertGetId([
            'slug' => 'test-' . uniqid(),
            'name' => json_encode(['ro' => 'Test', 'en' => 'Test']),
            'language' => 'ro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hymnal_songs')->insert([
            'hymnal_book_id' => $book,
            'number' => 1,
            'title' => json_encode(['ro' => 'X']),
            'stanzas' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('hymnal_songs')->insert([
            'hymnal_book_id' => $book,
            'number' => 1,
            'title' => json_encode(['ro' => 'Y']),
            'stanzas' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
