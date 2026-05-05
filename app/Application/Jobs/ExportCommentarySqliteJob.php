<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Commentary\Actions\ExportCommentarySqliteAction;
use App\Domain\Commentary\Models\Commentary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single-shot SQLite export job. Serialises per-slug exports through
 * `Cache::lock("sqlite-export:{slug}")` to keep two parallel exports
 * from picking the same `v{n}` revision (S3 listing is eventually
 * consistent).
 */
final class ExportCommentarySqliteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $importJobId,
        public readonly int $commentaryId,
    ) {
        $this->onConnection('database');
        $this->onQueue('exports');
    }

    public function handle(ExportCommentarySqliteAction $action): void
    {
        $importJob = ImportJob::query()->find($this->importJobId);
        if (! $importJob instanceof ImportJob) {
            return;
        }

        $commentary = Commentary::query()->find($this->commentaryId);
        if (! $commentary instanceof Commentary) {
            $importJob->update([
                'status' => ImportJobStatus::Failed,
                'error' => sprintf('Commentary #%d not found.', $this->commentaryId),
                'finished_at' => Carbon::now(),
            ]);

            return;
        }

        $importJob->update([
            'status' => ImportJobStatus::Running,
            'started_at' => Carbon::now(),
            'progress' => 0,
        ]);

        $lock = Cache::lock(sprintf('sqlite-export:%s', $commentary->slug), 600);

        try {
            $acquired = $lock->block(30);
            if (! $acquired) {
                $importJob->update([
                    'status' => ImportJobStatus::Failed,
                    'error' => 'Another SQLite export for this slug is in progress.',
                    'finished_at' => Carbon::now(),
                ]);

                return;
            }

            $result = $action->execute($commentary);

            $payload = is_array($importJob->payload) ? $importJob->payload : [];
            $payload['url'] = $result['url'];
            $payload['path'] = $result['path'];
            $payload['revision'] = $result['revision'];
            $payload['languages'] = $result['languages'];
            $payload['exported_at'] = $result['exported_at'];

            $importJob->update([
                'status' => ImportJobStatus::Succeeded,
                'progress' => 100,
                'payload' => $payload,
                'finished_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::error('ExportCommentarySqliteJob failed', [
                'import_job_id' => $this->importJobId,
                'commentary_id' => $this->commentaryId,
                'exception' => $e->getMessage(),
            ]);

            $importJob->update([
                'status' => ImportJobStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // best-effort
            }
        }
    }
}
