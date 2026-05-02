<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $any = false;

        foreach (['note', 'favorite_category', 'favorite', 'devotional_favorite', 'hymnal_favorite'] as $name) {
            if (Schema::hasTable($name)) {
                $any = true;
                break;
            }
        }

        if (! $any) {
            $this->ensureFavoriteUnique();

            return;
        }

        ReconcileTableHelper::rename('note', 'notes');
        ReconcileTableHelper::rename('favorite_category', 'favorite_categories');
        ReconcileTableHelper::rename('favorite', 'favorites');
        ReconcileTableHelper::rename('devotional_favorite', 'devotional_favorites');
        ReconcileTableHelper::rename('hymnal_favorite', 'hymnal_favorites');

        $this->ensureFavoriteUnique();
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('hymnal_favorites', 'hymnal_favorite');
        ReconcileTableHelper::rename('devotional_favorites', 'devotional_favorite');
        ReconcileTableHelper::rename('favorites', 'favorite');
        ReconcileTableHelper::rename('favorite_categories', 'favorite_category');
        ReconcileTableHelper::rename('notes', 'note');
    }

    private function ensureFavoriteUnique(): void
    {
        if (! Schema::hasTable('favorites')) {
            return;
        }

        if (! Schema::hasColumn('favorites', 'user_id') || ! Schema::hasColumn('favorites', 'category_id') || ! Schema::hasColumn('favorites', 'reference')) {
            return;
        }

        if ($this->hasIndex('favorites', 'favorites_user_category_ref_unique')) {
            return;
        }

        Schema::table('favorites', function (Blueprint $table): void {
            $table->unique(['user_id', 'category_id', 'reference'], 'favorites_user_category_ref_unique');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
