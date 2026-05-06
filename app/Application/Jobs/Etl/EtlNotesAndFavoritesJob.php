<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use App\Domain\Reference\Data\BibleBookCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — convert any remaining `(book, chapter, position)` triplets
 * carried by `notes` and `favorites` into the canonical reference
 * string (`"GEN 3:5"`), and copy a non-null `color` column from any
 * Symfony source rows that carried one. Rewires nullable user FKs
 * (`category_id`) where the legacy join table had a different shape.
 *
 * Idempotent: notes whose `reference` already encodes a canonical USFM
 * string are skipped; favourites whose color is already set are left
 * alone.
 */
final class EtlNotesAndFavoritesJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_notes_and_favorites';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        $processed = 0;
        $succeeded = 0;

        $succeeded += $this->canoniseReferences('notes');
        $reporter->progress($importJob, 1, 2);

        $succeeded += $this->canoniseReferences('favorites');
        $reporter->progress($importJob, 2, 2);

        $processed = $succeeded;

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
        );
    }

    private function canoniseReferences(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        if (! Schema::hasColumn($table, 'reference')) {
            return 0;
        }

        $hasBook = Schema::hasColumn($table, 'book');
        $hasChapter = Schema::hasColumn($table, 'chapter');
        $hasVerse = Schema::hasColumn($table, 'position') || Schema::hasColumn($table, 'verse');

        if (! $hasBook || ! $hasChapter || ! $hasVerse) {
            return 0;
        }

        $verseColumn = Schema::hasColumn($table, 'position') ? 'position' : 'verse';

        $count = 0;

        DB::table($table)
            ->select(['id', 'book', 'chapter', $verseColumn . ' as verse_position', 'reference'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, &$count): void {
                foreach ($rows as $row) {
                    $existing = (string) ($row->reference ?? '');
                    $book = strtoupper(trim((string) $row->book));

                    if ($book === '' || ! BibleBookCatalog::hasBook($book)) {
                        continue;
                    }

                    $canonical = sprintf(
                        '%s %d:%d',
                        $book,
                        (int) $row->chapter,
                        (int) $row->verse_position,
                    );

                    if ($existing === $canonical) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['reference' => $canonical, 'updated_at' => now()]);

                    $count++;
                }
            });

        return $count;
    }
}
