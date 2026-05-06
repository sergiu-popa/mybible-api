<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — intentionally NULL `users.preferred_language` for every
 * user so the post-login modal forces an explicit pick (per stakeholder
 * decision recorded in the story brief). The 3-char `users.language`
 * fallback is preserved until MBA-032 cleanup; mobile-only users keep
 * their existing language until they upgrade to a build that supports
 * the picker.
 *
 * Skips cleanly if the column is not present in the schema yet, so the
 * sub-job stays a no-op until the column lands.
 */
final class EtlUserPreferredLanguageJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_user_preferred_language';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('users')) {
            return new EtlSubJobResult;
        }

        if (! Schema::hasColumn('users', 'preferred_language')) {
            return new EtlSubJobResult(
                processed: 0,
                skipped: 0,
            );
        }

        $touched = (int) DB::affectingStatement(
            'UPDATE users SET preferred_language = NULL WHERE preferred_language IS NOT NULL',
        );

        $reporter->progress($importJob, 1, 1);

        return new EtlSubJobResult(
            processed: $touched,
            succeeded: $touched,
        );
    }
}
