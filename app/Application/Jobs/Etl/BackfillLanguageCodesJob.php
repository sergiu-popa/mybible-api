<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Actions\BackfillLegacyLanguageCodesAction;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;

/**
 * Stage 1 — rewrite Symfony's `varchar(3)` ISO-639-2 codes to ISO-639-1
 * across every reconciled table that still carries a `language` column.
 *
 * Idempotent: rows already in 2-char form pass through unchanged. Unknown
 * codes default to `'ro'` and are recorded in `security_events` by the
 * underlying action.
 */
final class BackfillLanguageCodesJob extends BaseEtlJob
{
    /**
     * Tables and columns that historically carried Symfony's 3-char codes.
     * Width-standardisation has already converted columns to `CHAR(2)` —
     * any residual 3-char value that snuck in would now truncate, so this
     * job runs as a post-cutover safety net rather than the primary
     * conversion (the schema migration handles that).
     */
    private const TARGETS = [
        ['users', 'language'],
        ['bible_versions', 'language'],
        ['resource_categories', 'language'],
        ['olympiad_questions', 'language'],
    ];

    public static function slug(): string
    {
        return 'etl_backfill_language_codes';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        $action = app(BackfillLegacyLanguageCodesAction::class);
        $processed = 0;

        foreach (self::TARGETS as [$table, $column]) {
            $action->handle($table, $column);
            $processed++;
            $reporter->progress($importJob, $processed, count(self::TARGETS));
        }

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $processed,
        );
    }
}
