<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use App\Domain\Migration\Exceptions\UnmappedLegacyBookException;
use App\Domain\Reference\Data\BibleBookCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — converts any remaining `(book, chapter, position)` triplets
 * carried by `notes` and `favorites` into the canonical reference
 * string (`"GEN 3:5"`), copies a non-null colour from any Symfony source
 * column that survived reconcile, and rewires the nullable
 * `favorites.category_id` FK from a legacy join table if one is still
 * present.
 *
 * Idempotent: rows whose `reference` already encodes a canonical USFM
 * string are skipped; rows whose `color` / `category_id` are already set
 * are left alone — operator-curated values cannot be stomped by a
 * re-run. Rows whose `book` value is not in {@see BibleBookCatalog} are
 * routed to `payload.errors` rather than silently skipped, so an
 * operator sees the unmapped value (Stage 1's `BackfillBookCodesJob`
 * should have folded these in already; anything left over is a true
 * exception).
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
        /** @var list<array{row?: int|string, message: string}> $errors */
        $errors = [];

        // 1. Run the Stage-1 normaliser one more time as a safety net so the
        //    canonisation step never trips on a long-form `book` value.
        $this->normaliseBookColumns($reporter, $importJob, $errors);

        // 2. Canonise (book, chapter, position) → "GEN 1:5".
        [$notesProcessed, $notesSucceeded] = $this->canoniseReferences('notes', $reporter, $importJob, $errors);
        $processed += $notesProcessed;
        $succeeded += $notesSucceeded;
        $reporter->progress($importJob, 1, 4);

        [$favProcessed, $favSucceeded] = $this->canoniseReferences('favorites', $reporter, $importJob, $errors);
        $processed += $favProcessed;
        $succeeded += $favSucceeded;
        $reporter->progress($importJob, 2, 4);

        // 3. Backfill colour from any legacy column that survived reconcile.
        $coloured = $this->backfillColors();
        $processed += $coloured;
        $succeeded += $coloured;
        $reporter->progress($importJob, 3, 4);

        // 4. Rewire `favorites.category_id` from any legacy join table.
        $rewired = $this->rewireFavoriteCategoryFk();
        $processed += $rewired;
        $succeeded += $rewired;
        $reporter->progress($importJob, 4, 4);

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
            errors: $errors,
        );
    }

    /**
     * @param  list<array{row?: int|string, message: string}>  $errors
     */
    private function normaliseBookColumns(EtlJobReporter $reporter, ImportJob $importJob, array &$errors): void
    {
        $action = app(BackfillLegacyBookAbbreviationsAction::class);

        foreach ([['notes', 'book'], ['favorites', 'reference']] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            try {
                $action->handle($table, $column);
            } catch (UnmappedLegacyBookException $exception) {
                $error = [
                    'row' => sprintf('%s.%s#%d', $table, $column, $exception->rowId),
                    'message' => $exception->getMessage(),
                ];
                $errors[] = $error;
                $reporter->appendError($importJob, $error);
            }
        }
    }

    /**
     * @param  list<array{row?: int|string, message: string}>  $errors
     * @return array{0: int, 1: int} processed, succeeded
     */
    private function canoniseReferences(
        string $table,
        EtlJobReporter $reporter,
        ImportJob $importJob,
        array &$errors,
    ): array {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'reference')) {
            return [0, 0];
        }

        $hasBook = Schema::hasColumn($table, 'book');
        $hasChapter = Schema::hasColumn($table, 'chapter');
        $hasVerse = Schema::hasColumn($table, 'position') || Schema::hasColumn($table, 'verse');

        if (! $hasBook || ! $hasChapter || ! $hasVerse) {
            return [0, 0];
        }

        $verseColumn = Schema::hasColumn($table, 'position') ? 'position' : 'verse';

        $processed = 0;
        $succeeded = 0;

        DB::table($table)
            ->select(['id', 'book', 'chapter', $verseColumn . ' as verse_position', 'reference'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, &$processed, &$succeeded, &$errors, $reporter, $importJob): void {
                foreach ($rows as $row) {
                    $processed++;
                    $existing = (string) ($row->reference ?? '');
                    $rawBook = (string) ($row->book ?? '');
                    $book = strtoupper(trim($rawBook));

                    if ($book === '') {
                        continue;
                    }

                    if (! BibleBookCatalog::hasBook($book)) {
                        $error = [
                            'row' => sprintf('%s#%d', $table, $row->id),
                            'message' => sprintf(
                                'Unmapped book "%s" — Stage 1 normaliser did not fold this value; reference left unchanged.',
                                $rawBook,
                            ),
                        ];
                        $errors[] = $error;
                        $reporter->appendError($importJob, $error);

                        continue;
                    }

                    $canonical = sprintf(
                        '%s %d:%d',
                        $book,
                        (int) $row->chapter,
                        (int) $row->verse_position,
                    );

                    if ($existing === $canonical) {
                        $succeeded++;

                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['reference' => $canonical, 'updated_at' => now()]);

                    $succeeded++;
                }
            });

        return [$processed, $succeeded];
    }

    /**
     * Copy a non-null `colour` (Symfony spelling) into the new `color`
     * column where one survives. For favorites, fall back to the linked
     * `favorite_categories.color` if neither column carries a value but
     * the category does — the legacy UX showed the category swatch on
     * uncolored favourites.
     */
    private function backfillColors(): int
    {
        $count = 0;

        foreach (['notes', 'favorites'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'color')) {
                continue;
            }

            if (Schema::hasColumn($table, 'colour')) {
                $count += (int) DB::affectingStatement(
                    "UPDATE {$table}
                     SET color = colour
                     WHERE color IS NULL AND colour IS NOT NULL AND colour <> ''",
                );
            }
        }

        if (
            Schema::hasTable('favorites')
            && Schema::hasColumn('favorites', 'color')
            && Schema::hasColumn('favorites', 'category_id')
            && Schema::hasTable('favorite_categories')
            && Schema::hasColumn('favorite_categories', 'color')
        ) {
            $count += (int) DB::affectingStatement(<<<'SQL'
                UPDATE favorites f
                JOIN favorite_categories fc ON fc.id = f.category_id
                SET f.color = fc.color
                WHERE f.color IS NULL AND fc.color IS NOT NULL AND fc.color <> ''
            SQL);
        }

        return $count;
    }

    /**
     * Rewire `favorites.category_id` from a Symfony-shaped legacy join
     * table that survived reconcile. The reconcile step left a map at
     * `_legacy_favorite_category_map` (legacy_favorite_id, category_id)
     * for any export where favourites used to live in a side table.
     * Only acts when both the legacy map and the target column exist
     * and the FK is still NULL.
     */
    private function rewireFavoriteCategoryFk(): int
    {
        if (! Schema::hasTable('favorites') || ! Schema::hasColumn('favorites', 'category_id')) {
            return 0;
        }

        if (! Schema::hasTable('_legacy_favorite_category_map')) {
            return 0;
        }

        return (int) DB::affectingStatement(<<<'SQL'
            UPDATE favorites f
            JOIN _legacy_favorite_category_map m ON m.legacy_favorite_id = f.id
            SET f.category_id = m.category_id
            WHERE f.category_id IS NULL
        SQL);
    }
}
