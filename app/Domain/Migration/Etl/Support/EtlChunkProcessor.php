<?php

declare(strict_types=1);

namespace App\Domain\Migration\Etl\Support;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drives a query-builder cursor through fixed-size chunks, invoking a
 * per-row callable inside a try/catch. Per-row failures are appended to
 * the result as error samples but do not abort iteration — sub-jobs
 * stay {@see ImportJobStatus::Partial}
 * rather than {@see ImportJobStatus::Failed}.
 */
final class EtlChunkProcessor
{
    public const DEFAULT_CHUNK = 500;

    /**
     * @param  callable(object): bool  $rowHandler  return `true` if the row was succeeded, `false` if skipped
     * @param  callable(int, int): void|null  $onProgress  total may be 0 when unknown
     */
    public function process(
        Builder $query,
        callable $rowHandler,
        ?callable $onProgress = null,
        string $primaryKey = 'id',
        int $chunk = self::DEFAULT_CHUNK,
    ): EtlSubJobResult {
        $total = (int) (clone $query)->count();
        $processed = 0;
        $succeeded = 0;
        $skipped = 0;
        /** @var list<array{row?: int|string, message: string}> $errors */
        $errors = [];

        $query
            ->orderBy($primaryKey)
            ->chunkById($chunk, function ($rows) use (
                $rowHandler,
                $onProgress,
                $total,
                &$processed,
                &$succeeded,
                &$skipped,
                &$errors,
                $primaryKey,
            ): void {
                foreach ($rows as $row) {
                    $processed++;

                    try {
                        $result = $rowHandler($row);
                        if ($result === true) {
                            $succeeded++;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable $exception) {
                        if (count($errors) < 50) {
                            $errors[] = [
                                'row' => $row->{$primaryKey} ?? null,
                                'message' => $exception->getMessage(),
                            ];
                        }
                    }
                }

                if ($onProgress !== null) {
                    $onProgress($processed, $total);
                }
            }, $primaryKey);

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
            skipped: $skipped,
            errors: $errors,
        );
    }

    /**
     * Wraps the iteration in a transaction that always rolls back. Used
     * by `--dry-run` to estimate row counts without writing.
     *
     * @param  callable(EtlChunkProcessor): EtlSubJobResult  $work
     */
    public function withRollback(callable $work): EtlSubJobResult
    {
        $result = null;

        DB::transaction(function () use ($work, &$result): void {
            $result = $work($this);
            DB::rollBack();
        });

        return $result instanceof EtlSubJobResult
            ? $result
            : new EtlSubJobResult;
    }
}
