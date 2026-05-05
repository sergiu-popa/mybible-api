<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\AI\Actions\AddReferencesAction;
use App\Domain\AI\DataTransferObjects\AddReferencesInput;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Async batch driver for AddReferences. Iterates rows of a target
 * collection, calls {@see AddReferencesAction} per row inside a
 * transaction, and updates the originating `import_jobs` row with
 * progress and final status.
 *
 * Until MBA-031 ships Horizon this runs on the existing `database`
 * queue worker — confirm `config/queue.php` retries cover the
 * per-row latency.
 */
final class AddReferencesBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly int $importJobId,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $language,
        public readonly array $filters = [],
    ) {}

    public function handle(AddReferencesAction $action): void
    {
        $importJob = ImportJob::query()->find($this->importJobId);
        if (! $importJob instanceof ImportJob) {
            return;
        }

        $importJob->update([
            'status' => ImportJobStatus::Running,
            'started_at' => Carbon::now(),
            'progress' => 0,
        ]);

        try {
            $rows = $this->loadTargetRows();
            $total = max(1, count($rows));
            $processed = 0;

            $triggeredByUserId = $importJob->user_id;

            foreach ($rows as $row) {
                DB::transaction(function () use ($action, $row, $triggeredByUserId): void {
                    $output = $action->execute(new AddReferencesInput(
                        html: (string) ($row['html'] ?? ''),
                        language: $this->language,
                        bibleVersionAbbreviation: null,
                        subjectType: $this->subjectType,
                        subjectId: isset($row['id']) ? (int) $row['id'] : null,
                        triggeredByUserId: $triggeredByUserId,
                    ));

                    $this->writeBack($row, $output->html);
                });

                $processed++;
                $importJob->update([
                    'progress' => (int) min(100, floor(($processed / $total) * 100)),
                ]);
            }

            $importJob->update([
                'status' => ImportJobStatus::Succeeded,
                'progress' => 100,
                'finished_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::error('AddReferencesBatchJob failed', [
                'import_job_id' => $this->importJobId,
                'exception' => $e->getMessage(),
            ]);

            $importJob->update([
                'status' => ImportJobStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Stub — the per-subject row enumeration lands with the consuming
     * stories (commentary AI workflow MBA-029, devotional batch
     * scaffolding). For the foundation story the job is wired so the
     * sync tests pass without iterating any rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadTargetRows(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function writeBack(array $row, string $html): void
    {
        // Per-subject persistence is implemented by consumer stories.
        // Kept here as the single seam batch processors will call.
    }
}
