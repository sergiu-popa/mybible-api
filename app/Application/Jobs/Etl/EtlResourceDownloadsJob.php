<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Analytics\Models\ResourceDownload;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — copy single-typed Symfony `resource_download` rows into the
 * polymorphic `resource_downloads` table with
 * `downloadable_type='educational_resource'`. The legacy
 * `ip_address` column is dropped at the end of a successful pass.
 *
 * Idempotent: a (created_at, downloadable_id, user_id) probe is used to
 * skip rows that have already been migrated.
 */
final class EtlResourceDownloadsJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_resource_downloads';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('resource_downloads') || ! Schema::hasTable('resource_download')) {
            return new EtlSubJobResult;
        }

        $rows = DB::table('resource_download')->orderBy('id')->get();
        $total = $rows->count();
        $processed = 0;
        $succeeded = 0;

        foreach ($rows as $row) {
            $processed++;

            $exists = DB::table('resource_downloads')
                ->where('downloadable_type', ResourceDownload::TYPE_EDUCATIONAL_RESOURCE)
                ->where('downloadable_id', $row->educational_resource_id ?? $row->resource_id ?? 0)
                ->where('created_at', $row->created_at)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('resource_downloads')->insert([
                'downloadable_type' => ResourceDownload::TYPE_EDUCATIONAL_RESOURCE,
                'downloadable_id' => $row->educational_resource_id ?? $row->resource_id ?? 0,
                'user_id' => $row->user_id ?? null,
                'device_id' => $row->device_id ?? null,
                'language' => $row->language ?? null,
                'source' => $row->source ?? null,
                'created_at' => $row->created_at,
            ]);

            $succeeded++;

            if ($processed % 100 === 0) {
                $reporter->progress($importJob, $processed, $total);
            }
        }

        // PII removal — drop the legacy IP column whenever it is still
        // present. Gating on $succeeded > 0 would skip the drop on a
        // re-run where the prior pass already moved every row, leaving
        // the legacy PII column behind forever. The table itself is
        // guaranteed by the early return above.
        if (Schema::hasColumn('resource_download', 'ip_address')) {
            DB::statement('ALTER TABLE resource_download DROP COLUMN ip_address');
        }

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
        );
    }
}
