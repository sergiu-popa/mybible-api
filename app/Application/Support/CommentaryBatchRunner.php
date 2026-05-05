<?php

declare(strict_types=1);

namespace App\Application\Support;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Shared chunk/transaction/per-row error-trail helper for the three
 * commentary batch jobs (correct, add-references, translate).
 *
 * The runner:
 * - chunks the target Builder in {@see self::DEFAULT_CHUNK_SIZE} batches
 * - runs the per-row callable inside a transaction
 * - accumulates per-row failures into `import_jobs.error` (JSON list)
 * - updates `progress` per chunk
 * - returns the terminal {@see ImportJobStatus}
 *   (`Succeeded` if every row ok, `Partial` if any row failed)
 *
 * @phpstan-type RowFailure array{id: int, message: string}
 */
final class CommentaryBatchRunner
{
    public const DEFAULT_CHUNK_SIZE = 50;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(TModel): void  $perRow
     */
    public function run(
        ImportJob $importJob,
        Builder $query,
        Closure $perRow,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
    ): ImportJobStatus {
        $importJob->update([
            'status' => ImportJobStatus::Running,
            'started_at' => $importJob->started_at ?? Carbon::now(),
            'progress' => 0,
            'error' => null,
        ]);

        $total = (clone $query)->count();
        if ($total === 0) {
            $importJob->update([
                'status' => ImportJobStatus::Succeeded,
                'progress' => 100,
                'finished_at' => Carbon::now(),
            ]);

            return ImportJobStatus::Succeeded;
        }

        /** @var list<RowFailure> $failures */
        $failures = [];
        $processed = 0;

        $query->chunkById($chunkSize, function ($rows) use (&$failures, &$processed, $perRow, $importJob, $total): void {
            foreach ($rows as $row) {
                /** @var Model $row */
                try {
                    DB::transaction(static function () use ($perRow, $row): void {
                        /** @var Closure(Model): void $perRow */
                        $perRow($row);
                    });
                } catch (Throwable $e) {
                    $failures[] = [
                        'id' => (int) $row->getKey(),
                        'message' => $e->getMessage(),
                    ];
                }

                $processed++;
            }

            $importJob->update([
                'progress' => (int) min(100, floor(($processed / max(1, $total)) * 100)),
            ]);
        });

        $finalStatus = count($failures) === 0
            ? ImportJobStatus::Succeeded
            : ImportJobStatus::Partial;

        $update = [
            'status' => $finalStatus,
            'progress' => 100,
            'finished_at' => Carbon::now(),
        ];

        if (count($failures) > 0) {
            $payload = is_array($importJob->payload) ? $importJob->payload : [];
            $payload['failures'] = $failures;
            $update['payload'] = $payload;
            $update['error'] = sprintf('%d row(s) failed.', count($failures));
        }

        $importJob->update($update);

        return $finalStatus;
    }
}
