<?php

declare(strict_types=1);

namespace App\Domain\Migration\Etl\Support;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Opens and closes one `import_jobs` row per ETL sub-job invocation,
 * maintains a progress percentage, and translates an
 * {@see EtlSubJobResult} into the appropriate terminal status. Idempotent
 * across re-runs: a `succeeded` row for the same `type` short-circuits
 * `start()` so resume mode skips it.
 */
final class EtlJobReporter
{
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
    }

    public function fail(ImportJob $job, Throwable $exception): void
    {
        $job->update([
            'status' => ImportJobStatus::Failed,
            'error' => $exception->getMessage(),
            'finished_at' => Carbon::now(),
        ]);
    }
}
