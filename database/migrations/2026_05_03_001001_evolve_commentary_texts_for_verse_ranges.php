<?php

declare(strict_types=1);

use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the `commentary_texts` table to the Laravel-final shape. In a
 * fresh environment the create migration laid down every column and
 * index this migration touches, so each branch is a no-op. In production
 * the renamed Symfony table arrives with `commentary` (legacy FK
 * column), narrow `book`, no verse-range columns, and the legacy
 * `chapter_idx`; this migration evolves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commentary_texts')) {
            return;
        }

        ReconcileTableHelper::renameColumnIfPresent('commentary_texts', 'commentary', 'commentary_id');

        $this->ensureCommentaryIdForeignKey();
        $this->widenBookColumn();

        (new BackfillLegacyBookAbbreviationsAction)->handle('commentary_texts', 'book');

        $this->ensureVerseRangeColumns();
        $this->backfillVerseRangeFromPosition();
        $this->dropLegacyChapterIndex();
        $this->ensureUniquePosition();
        $this->ensureVerseLookupIndex();
    }

    public function down(): void
    {
        // No-op: this migration is destructive forward (drops legacy
        // index, widens columns) and there is no useful inverse.
    }

    private function ensureCommentaryIdForeignKey(): void
    {
        if (! Schema::hasColumn('commentary_texts', 'commentary_id')) {
            return;
        }

        foreach (Schema::getForeignKeys('commentary_texts') as $foreign) {
            $columns = $foreign['columns'] ?? [];

            if (in_array('commentary_id', $columns, true)) {
                return;
            }
        }

        Schema::table('commentary_texts', function (Blueprint $table): void {
            $table->foreign('commentary_id')
                ->references('id')
                ->on('commentaries')
                ->cascadeOnDelete();
        });
    }

    private function widenBookColumn(): void
    {
        if (! Schema::hasColumn('commentary_texts', 'book')) {
            return;
        }

        Schema::table('commentary_texts', function (Blueprint $table): void {
            $table->string('book', 8)->change();
        });
    }

    private function ensureVerseRangeColumns(): void
    {
        Schema::table('commentary_texts', function (Blueprint $table): void {
            if (! Schema::hasColumn('commentary_texts', 'verse_from')) {
                $table->unsignedSmallInteger('verse_from')->nullable()->after('position');
            }

            if (! Schema::hasColumn('commentary_texts', 'verse_to')) {
                $table->unsignedSmallInteger('verse_to')->nullable()->after('verse_from');
            }

            if (! Schema::hasColumn('commentary_texts', 'verse_label')) {
                $table->string('verse_label', 20)->nullable()->after('verse_to');
            }
        });
    }

    /**
     * Symfony stored verse number directly in `position`. Treat each
     * position as a single-verse block and seed the new range columns.
     * Skip rows where the columns are already populated (re-run safe)
     * or where `position` is non-positive (chapter intros etc.) — those
     * keep NULL ranges and admin can fill in later.
     */
    private function backfillVerseRangeFromPosition(): void
    {
        DB::table('commentary_texts')
            ->whereNull('verse_from')
            ->whereNull('verse_to')
            ->whereNull('verse_label')
            ->where('position', '>', 0)
            ->orderBy('id')
            ->each(function ($row): void {
                $position = (int) $row->position;

                DB::table('commentary_texts')
                    ->where('id', $row->id)
                    ->update([
                        'verse_from' => $position,
                        'verse_to' => $position,
                        'verse_label' => (string) $position,
                    ]);
            });
    }

    private function dropLegacyChapterIndex(): void
    {
        foreach (Schema::getIndexes('commentary_texts') as $index) {
            if (($index['name'] ?? null) === 'chapter_idx') {
                Schema::table('commentary_texts', function (Blueprint $table): void {
                    $table->dropIndex('chapter_idx');
                });

                return;
            }
        }
    }

    private function ensureUniquePosition(): void
    {
        if ($this->hasIndex('commentary_texts_unique_position')) {
            return;
        }

        Schema::table('commentary_texts', function (Blueprint $table): void {
            $table->unique(
                ['commentary_id', 'book', 'chapter', 'position'],
                'commentary_texts_unique_position',
            );
        });
    }

    private function ensureVerseLookupIndex(): void
    {
        if ($this->hasIndex('commentary_texts_verse_lookup_idx')) {
            return;
        }

        Schema::table('commentary_texts', function (Blueprint $table): void {
            $table->index(
                ['commentary_id', 'book', 'chapter', 'verse_from', 'verse_to'],
                'commentary_texts_verse_lookup_idx',
            );
        });
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('commentary_texts') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
