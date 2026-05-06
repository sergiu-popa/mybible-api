<?php

declare(strict_types=1);

namespace App\Domain\Migration\Etl\Support;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Opens and closes one `import_jobs` row per ETL sub-job invocation,
 * maintains a progress percentage, and translates an
 * {@see EtlSubJobResult} into the appropriate terminal status.
 *
 * Idempotency:
 *   • If a `Succeeded` or `Partial` row already exists for the given type,
 *     {@see start()} surfaces it and the caller is expected to short-circuit.
 *   • If a stale `Running` row is left over from a crashed previous run
 *     (older than {@see STALE_RUNNING_THRESHOLD_HOURS} hours), it is
 *     transitioned to `Failed` so subsequent runs see a clean slate
 *     instead of accumulating orphan rows.
 */
final class EtlJobReporter
{
    /**
     * Running rows older than this many hours are considered orphaned by
     * a crashed previous run and are flipped to {@see ImportJobStatus::Failed}
     * before {@see start()} creates a fresh row. The story estimates a
     * 30–60 minute total ETL run, so 2h leaves comfortable headroom.
     */
    public const STALE_RUNNING_THRESHOLD_HOURS = 2;

    public function start(string $type): ImportJob
    {
        $existing = ImportJob::query()
            ->where('type', $type)
            ->whereIn('status', [ImportJobStatus::Succeeded, ImportJobStatus::Partial])
            ->latest('id')
            ->first();

        if ($existing instanceof ImportJob) {
            return $existing;
        }

        $this->retireStaleRunningRows($type);

        return ImportJob::query()->create([
            'type' => $type,
            'status' => ImportJobStatus::Running,
            'progress' => 0,
            'payload' => [],
            'started_at' => Carbon::now(),
        ]);
    }

    public function isAlreadyTerminal(ImportJob $job): bool
    {
        return $job->status->isTerminal()
            && $job->status !== ImportJobStatus::Failed;
    }

    public function progress(ImportJob $job, int $processed, int $total): void
    {
        $percentage = $total > 0
            ? (int) min(99, floor(($processed / $total) * 100))
            : 0;

        if ($percentage <= $job->progress) {
            return;
        }

        $job->update(['progress' => $percentage]);
    }

    /**
     * @param  array{row?: int|string, message: string}  $error
     */
    public function appendError(ImportJob $job, array $error): void
    {
        $payload = is_array($job->payload) ? $job->payload : [];
        $errors = is_array($payload['errors'] ?? null) ? $payload['errors'] : [];

        if (count($errors) >= 50) {
            $payload['error_count_overflow'] = (int) ($payload['error_count_overflow'] ?? 0) + 1;
        } else {
            $errors[] = $error;
            $payload['errors'] = $errors;
        }

        $job->update(['payload' => $payload]);
    }

    public function complete(ImportJob $job, EtlSubJobResult $result): void
    {
        $payload = is_array($job->payload) ? $job->payload : [];
        $payload = array_merge($payload, $result->toPayload());

        $status = match (true) {
            $result->isFailed() => ImportJobStatus::Failed,
            $result->isPartial() => ImportJobStatus::Partial,
            default => ImportJobStatus::Succeeded,
        };

        $job->update([
            'status' => $status,
            'progress' => 100,
            'payload' => $payload,
            'finished_at' => Carbon::now(),
        ]);

        if ($status === ImportJobStatus::Partial) {
            $this->emitPartialSecurityEvent($job, $result);
        }
    }

    public function fail(ImportJob $job, Throwable $exception): void
    {
        $job->update([
            'status' => ImportJobStatus::Failed,
            'error' => $exception->getMessage(),
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark Running rows whose `started_at` is older than the stale
     * threshold as Failed. Without this, a crashed previous run leaves
     * the type pinned to a phantom Running row forever, and successive
     * `--resume` attempts pile up further orphans of the same type.
     */
    private function retireStaleRunningRows(string $type): void
    {
        $cutoff = Carbon::now()->subHours(self::STALE_RUNNING_THRESHOLD_HOURS);

        ImportJob::query()
            ->where('type', $type)
            ->where('status', ImportJobStatus::Running)
            ->where(function ($query) use ($cutoff): void {
                $query->where('started_at', '<', $cutoff)
                    ->orWhereNull('started_at');
            })
            ->update([
                'status' => ImportJobStatus::Failed,
                'error' => 'Retired by EtlJobReporter: stale Running row from a crashed previous run.',
                'finished_at' => Carbon::now(),
            ]);
    }

    /**
     * A `Partial` outcome means some rows transformed successfully and
     * some did not; routing it through `security_events` keeps the
     * Auditor happy and gives operators a single audit timeline regardless
     * of which sub-job degraded.
     */
    private function emitPartialSecurityEvent(ImportJob $job, EtlSubJobResult $result): void
    {
        if (! Schema::hasTable('security_events')) {
            return;
        }

        DB::table('security_events')->insert([
            'event' => 'etl_sub_job_partial',
            'reason' => sprintf(
                'ETL sub-job "%s" finished with %d/%d rows succeeded.',
                $job->type,
                $result->succeeded,
                $result->processed,
            ),
            'affected_count' => count($result->errors),
            'metadata' => json_encode([
                'import_job_id' => $job->id,
                'sub_job' => $job->type,
                'processed' => $result->processed,
                'succeeded' => $result->succeeded,
                'skipped' => $result->skipped,
                'error_count' => count($result->errors),
            ], JSON_UNESCAPED_UNICODE),
            'occurred_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);
    }
}
