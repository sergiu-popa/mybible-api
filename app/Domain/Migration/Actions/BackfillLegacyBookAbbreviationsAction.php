<?php

declare(strict_types=1);

namespace App\Domain\Migration\Actions;

use App\Domain\Migration\Exceptions\UnmappedLegacyBookException;
use App\Domain\Reference\Data\BibleBookCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Walk a (table, column) carrying Symfony-shaped book identifiers (long
 * Romanian/English names like "Genesis", "1 Corinthians") and rewrite
 * each value to USFM-3 using `_legacy_book_abbreviation_map`. A value
 * already in USFM-3 form passes through unchanged. Anything missing
 * from the map raises {@see UnmappedLegacyBookException} so the
 * migration aborts loudly per AC §14.
 */
final class BackfillLegacyBookAbbreviationsAction
{
    private const CHUNK = 500;

    public function handle(string $table, string $column, string $primaryKey = 'id'): void
    {
        if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
            return;
        }

        if (! DB::getSchemaBuilder()->hasTable('_legacy_book_abbreviation_map')) {
            return;
        }

        $cache = [];

        DB::table($table)
            ->select([$primaryKey, $column])
            ->whereNotNull($column)
            ->orderBy($primaryKey)
            ->chunkById(self::CHUNK, function ($rows) use ($table, $column, $primaryKey, &$cache): void {
                foreach ($rows as $row) {
                    $original = (string) $row->{$column};
                    $trimmed = trim($original);

                    if ($trimmed === '') {
                        continue;
                    }

                    if (BibleBookCatalog::hasBook(strtoupper($trimmed))) {
                        $usfm = strtoupper($trimmed);

                        if ($usfm === $original) {
                            continue;
                        }

                        DB::table($table)
                            ->where($primaryKey, $row->{$primaryKey})
                            ->update([$column => $usfm]);

                        continue;
                    }

                    $key = mb_strtolower($trimmed);

                    if (! array_key_exists($key, $cache)) {
                        $cache[$key] = DB::table('_legacy_book_abbreviation_map')
                            ->whereRaw('LOWER(name) = ?', [$key])
                            ->value('abbreviation');
                    }

                    $usfm = $cache[$key];

                    if (! is_string($usfm) || $usfm === '') {
                        throw new UnmappedLegacyBookException(
                            $table,
                            $column,
                            (int) $row->{$primaryKey},
                            $original,
                        );
                    }

                    DB::table($table)
                        ->where($primaryKey, $row->{$primaryKey})
                        ->update([$column => $usfm]);
                }
            }, $primaryKey);
    }
}
