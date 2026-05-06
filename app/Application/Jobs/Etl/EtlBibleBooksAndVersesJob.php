<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — finishes the bible-books reconcile that started at schema
 * level. Reconcile already deduped Symfony's per-version `book` rows
 * into a global `bible_books` table and left a `_legacy_book_map` for
 * the row-level rewire. This job uses that map to populate
 * `bible_verses.bible_book_id` / `bible_version_id` (idempotent: only
 * touches rows where the FK is NULL) and synthesises `bible_chapters`
 * rows from per-(version,book) verse counts.
 */
final class EtlBibleBooksAndVersesJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_bible_books_and_verses';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('bible_verses')) {
            return new EtlSubJobResult;
        }

        $rewired = $this->rewireVerseFks();
        $reporter->progress($importJob, 1, 2);

        $chapters = $this->populateChapters();
        $reporter->progress($importJob, 2, 2);

        return new EtlSubJobResult(
            processed: $rewired + $chapters,
            succeeded: $rewired + $chapters,
        );
    }

    private function rewireVerseFks(): int
    {
        // Skip when reconcile didn't leave a legacy map (fresh CI / dev
        // schema), or when the verse table never carried the legacy
        // `book_id` column (already imported pristine).
        if (! Schema::hasTable('_legacy_book_map')) {
            return 0;
        }

        if (! Schema::hasColumn('bible_verses', 'bible_book_id')) {
            return 0;
        }

        if (! Schema::hasColumn('bible_verses', 'book_id')) {
            return 0;
        }

        $sql = <<<'SQL'
            UPDATE bible_verses bv
            JOIN _legacy_book_map m ON m.legacy_book_id = bv.book_id
            SET bv.bible_book_id = m.bible_book_id,
                bv.bible_version_id = m.bible_version_id
            WHERE bv.bible_book_id IS NULL
        SQL;

        return (int) DB::affectingStatement($sql);
    }

    private function populateChapters(): int
    {
        if (! Schema::hasTable('bible_chapters')) {
            return 0;
        }

        // `bible_chapters` is global per book (unique on bible_book_id,number).
        // Verse counts may diverge across versions; pick the maximum so the
        // chapter advertises every reachable verse position.
        $sql = <<<'SQL'
            INSERT INTO bible_chapters (bible_book_id, `number`, verse_count, created_at, updated_at)
            SELECT bible_book_id, chapter, MAX(verse_max) AS verse_count, NOW(), NOW()
            FROM (
                SELECT bible_book_id, chapter, MAX(verse) AS verse_max
                FROM bible_verses
                WHERE bible_book_id IS NOT NULL
                GROUP BY bible_version_id, bible_book_id, chapter
            ) per_version
            GROUP BY bible_book_id, chapter
            ON DUPLICATE KEY UPDATE
                verse_count = GREATEST(verse_count, VALUES(verse_count)),
                updated_at = NOW()
        SQL;

        return (int) DB::affectingStatement($sql);
    }
}
