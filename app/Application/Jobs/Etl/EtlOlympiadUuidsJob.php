<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stage 2 — populate `uuid` on every `olympiad_questions` and
 * `olympiad_answers` row that is still missing one. Idempotent because
 * the WHERE clause filters on NULL/empty, and every generated value is
 * unique by construction.
 */
final class EtlOlympiadUuidsJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_olympiad_uuids';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        $tables = ['olympiad_questions', 'olympiad_answers'];
        $processed = 0;
        $succeeded = 0;
        $total = count($tables);

        foreach ($tables as $index => $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
                $reporter->progress($importJob, $index + 1, $total);

                continue;
            }

            $rows = DB::table($table)
                ->where(function ($query): void {
                    $query->whereNull('uuid')->orWhere('uuid', '');
                })
                ->orderBy('id')
                ->select('id')
                ->get();

            foreach ($rows as $row) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid(), 'updated_at' => now()]);
                $succeeded++;
            }

            $processed += $rows->count();
            $reporter->progress($importJob, $index + 1, $total);
        }

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
        );
    }
}
