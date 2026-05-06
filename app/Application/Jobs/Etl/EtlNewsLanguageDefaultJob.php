<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — defensive ETL safety net for `news`:
 *   • language NULL/empty → 'ro'
 *   • published_at NULL → created_at
 *
 * The same defaults are applied at the schema migration; this job
 * exists for the case where rows arrive late (e.g. a partial Symfony
 * dump replayed mid-cutover). Idempotent.
 */
final class EtlNewsLanguageDefaultJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_news_language_default';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('news')) {
            return new EtlSubJobResult;
        }

        $touched = 0;

        if (Schema::hasColumn('news', 'language')) {
            $touched += (int) DB::affectingStatement(
                "UPDATE news SET language = 'ro' WHERE language IS NULL OR language = ''",
            );
        }

        $reporter->progress($importJob, 1, 2);

        if (Schema::hasColumn('news', 'published_at')) {
            $touched += (int) DB::affectingStatement(
                'UPDATE news SET published_at = created_at WHERE published_at IS NULL',
            );
        }

        $reporter->progress($importJob, 2, 2);

        return new EtlSubJobResult(
            processed: $touched,
            succeeded: $touched,
        );
    }
}
