<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use App\Domain\Migration\Exceptions\UnmappedLegacyBookException;
use Throwable;

/**
 * Stage 1 — rewrite long-form / Romanian book values to USFM-3 across
 * tables that store a book identifier as a string. Per AC §14, an
 * unmapped value is a hard failure for that row but the sub-job
 * continues with the remaining (table, column) pairs and surfaces the
 * offending values as `payload.errors`.
 */
final class BackfillBookCodesJob extends BaseEtlJob
{
    private const TARGETS = [
        ['olympiad_questions', 'book'],
        ['notes', 'book'],
        ['favorites', 'reference'],
    ];

    public static function slug(): string
    {
        return 'etl_backfill_book_codes';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        $action = app(BackfillLegacyBookAbbreviationsAction::class);
        $processed = 0;
        $succeeded = 0;
        /** @var list<array{row?: int|string, message: string}> $errors */
        $errors = [];

        foreach (self::TARGETS as [$table, $column]) {
            $processed++;

            try {
                $action->handle($table, $column);
                $succeeded++;
            } catch (UnmappedLegacyBookException $exception) {
                $errors[] = [
                    'row' => sprintf('%s.%s#%d', $table, $column, $exception->rowId),
                    'message' => $exception->getMessage(),
                ];
                $reporter->appendError($importJob, $errors[count($errors) - 1]);
            } catch (Throwable $exception) {
                $errors[] = [
                    'row' => sprintf('%s.%s', $table, $column),
                    'message' => $exception->getMessage(),
                ];
                $reporter->appendError($importJob, $errors[count($errors) - 1]);
            }

            $reporter->progress($importJob, $processed, count(self::TARGETS));
        }

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
            errors: $errors,
        );
    }
}
