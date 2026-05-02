<?php

declare(strict_types=1);

namespace App\Domain\Migration\Actions;

use App\Domain\Migration\Support\LegacyLanguageCodeMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Walk a (table, column) carrying Symfony's 3-char language codes and
 * rewrite each row to the Laravel-shaped 2-char code. Unknown codes
 * default to `'ro'` and emit one `security_events` row per defaulted
 * value for operator review.
 */
final class BackfillLegacyLanguageCodesAction
{
    private const DEFAULT_CODE = 'ro';

    private const CHUNK = 500;

    public function handle(string $table, string $column, string $primaryKey = 'id'): void
    {
        if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->select([$primaryKey, $column])
            ->whereNotNull($column)
            ->orderBy($primaryKey)
            ->chunkById(self::CHUNK, function ($rows) use ($table, $column, $primaryKey): void {
                foreach ($rows as $row) {
                    $original = (string) $row->{$column};

                    if ($original === '') {
                        continue;
                    }

                    $mapped = LegacyLanguageCodeMap::to2Char($original);

                    if ($mapped === null) {
                        $this->logDefault($table, $column, $row->{$primaryKey}, $original);
                        $mapped = self::DEFAULT_CODE;
                    }

                    if ($mapped === $original) {
                        continue;
                    }

                    DB::table($table)
                        ->where($primaryKey, $row->{$primaryKey})
                        ->update([$column => $mapped]);
                }
            }, $primaryKey);
    }

    private function logDefault(string $table, string $column, mixed $rowId, string $original): void
    {
        if (! DB::getSchemaBuilder()->hasTable('security_events')) {
            return;
        }

        DB::table('security_events')->insert([
            'event' => 'language_backfill_default',
            'reason' => sprintf(
                'Unmapped legacy language code "%s" in %s.%s defaulted to "%s".',
                $original,
                $table,
                $column,
                self::DEFAULT_CODE,
            ),
            'affected_count' => 1,
            'metadata' => json_encode([
                'original_code' => $original,
                'table' => $table,
                'column' => $column,
                'row_id' => $rowId,
            ]),
            'occurred_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);
    }
}
