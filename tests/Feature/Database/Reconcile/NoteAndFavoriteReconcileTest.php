<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

final class NoteAndFavoriteReconcileTest extends ReconcileTestCase
{
    use RefreshDatabase;

    public function test_it_renames_note_and_favorite_tables(): void
    {
        Schema::dropIfExists('hymnal_favorites');
        Schema::dropIfExists('devotional_favorites');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('favorite_categories');
        Schema::dropIfExists('notes');

        $this->recreateLegacyTable('note', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('book', 8);
            $t->string('text', 255);
            $t->timestamps();
        });
        $this->recreateLegacyTable('favorite_category', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        $this->recreateLegacyTable('favorite', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('category_id');
            $t->string('reference', 100);
            $t->timestamps();
        });
        $this->recreateLegacyTable('devotional_favorite', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('devotional_id');
        });
        $this->recreateLegacyTable('hymnal_favorite', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('hymnal_song_id');
        });

        $migration = $this->loadMigration('2026_05_03_000210_reconcile_symfony_note_and_favorite_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $this->assertTableExists('notes');
        $this->assertTableExists('favorite_categories');
        $this->assertTableExists('favorites');
        $this->assertTableExists('devotional_favorites');
        $this->assertTableExists('hymnal_favorites');

        $this->assertTableMissing('note');
        $this->assertTableMissing('favorite_category');
        $this->assertTableMissing('favorite');
        $this->assertTableMissing('devotional_favorite');
        $this->assertTableMissing('hymnal_favorite');
    }

    public function test_favorites_unique_blocks_duplicate_user_category_reference(): void
    {
        $user = User::factory()->create();

        $migration = $this->loadMigration('2026_05_03_000210_reconcile_symfony_note_and_favorite_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $now = now();

        $categoryId = DB::table('favorite_categories')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Default',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('favorites')->insert([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'reference' => 'GEN.1:1.VDC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('favorites')->insert([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'reference' => 'GEN.1:1.VDC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
