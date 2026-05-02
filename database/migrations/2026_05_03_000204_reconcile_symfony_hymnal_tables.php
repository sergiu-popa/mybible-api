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
        if (! Schema::hasTable('hymnal_book') && ! Schema::hasTable('hymnal_song') && ! Schema::hasTable('hymnal_verse')) {
            $this->ensureSongUnique();

            return;
        }

        ReconcileTableHelper::rename('hymnal_book', 'hymnal_books');
        ReconcileTableHelper::rename('hymnal_song', 'hymnal_songs');
        ReconcileTableHelper::rename('hymnal_verse', 'hymnal_verses');

        $this->ensureSongUnique();
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('hymnal_verses', 'hymnal_verse');
        ReconcileTableHelper::rename('hymnal_songs', 'hymnal_song');
        ReconcileTableHelper::rename('hymnal_books', 'hymnal_book');
    }

    private function ensureSongUnique(): void
    {
        if (! Schema::hasTable('hymnal_songs')) {
            return;
        }

        if (! Schema::hasColumn('hymnal_songs', 'hymnal_book_id') || ! Schema::hasColumn('hymnal_songs', 'number')) {
            return;
        }

        if ($this->hasIndex('hymnal_songs', 'hymnal_songs_book_number_unique')) {
            return;
        }

        Schema::table('hymnal_songs', function (Blueprint $table): void {
            $table->unique(['hymnal_book_id', 'number'], 'hymnal_songs_book_number_unique');
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
