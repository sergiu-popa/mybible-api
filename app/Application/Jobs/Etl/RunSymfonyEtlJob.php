<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlRunOptions;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Top-level orchestrator. Opens an `ImportJob` of `type='symfony_etl'`,
 * emits a `security_events` row, then dispatches a `Bus::chain` of three
 * batches:
 *   • Stage 1 — identifier normalisation (parallelisable).
 *   • Stage 2a — domain ETL not depending on SS content rows.
 *   • Stage 2b — Sabbath School highlights (depends on 2a's content rows).
 *
 * On chain success the orchestrator marks itself succeeded and emits a
 * `symfony_etl_completed` event; on chain failure the failure event is
 * emitted and the row stays `Failed` so `--resume` re-runs the
 * unfinished sub-jobs.
 */
final class RunSymfonyEtlJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var list<class-string<BaseEtlJob>> */
    public const STAGE_1 = [
        BackfillLanguageCodesJob::class,
        BackfillBookCodesJob::class,
    ];

    /** @var list<class-string<BaseEtlJob>> */
    public const STAGE_2A = [
        EtlBibleBooksAndVersesJob::class,
        EtlHymnalStanzasJob::class,
        EtlReadingPlansJob::class,
        EtlReadingPlanSubscriptionsJob::class,
        EtlSabbathSchoolContentJob::class,
        EtlSabbathSchoolQuestionsJob::class,
        EtlDevotionalTypesJob::class,
        EtlMobileVersionsSeedJob::class,
        EtlCollectionsParentJob::class,
        EtlOlympiadUuidsJob::class,
        EtlResourceDownloadsJob::class,
        EtlNewsLanguageDefaultJob::class,
        EtlNotesAndFavoritesJob::class,
        EtlUserPreferredLanguageJob::class,
    ];

    /** @var list<class-string<BaseEtlJob>> */
    public const STAGE_2B = [
        EtlSabbathSchoolHighlightsJob::class,
    ];

    public function __construct(public readonly EtlRunOptions $options)
    {
        $this->onQueue('etl');
    }

    public function handle(EtlJobReporter $reporter): void
    {
        $orchestratorJob = $reporter->start('symfony_etl');
        $orchestratorJobId = (int) $orchestratorJob->id;

        $this->emitSecurityEvent('symfony_etl_started', $orchestratorJobId);

        $stage1 = $this->buildStage(self::STAGE_1, $reporter);
        $stage2a = $this->buildStage(self::STAGE_2A, $reporter);
        $stage2b = $this->buildStage(self::STAGE_2B, $reporter);

        $batches = [];
        if ($stage1 !== []) {
            $batches[] = Bus::batch($stage1)->name('etl-stage-1');
        }
        if ($stage2a !== []) {
            $batches[] = Bus::batch($stage2a)->name('etl-stage-2a');
        }
        if ($stage2b !== []) {
            $batches[] = Bus::batch($stage2b)->name('etl-stage-2b');
        }

        if ($batches === []) {
            $reporter->complete(
                $orchestratorJob,
                new EtlSubJobResult(processed: 0, succeeded: 0),
            );
            $this->emitSecurityEvent('symfony_etl_completed', $orchestratorJobId);

            return;
        }

        Bus::chain([
            ...$batches,
            $this->finishCallback($orchestratorJobId, $this->options),
        ])
            ->catch($this->onFailure($orchestratorJobId))
            ->dispatch();
    }

    /**
     * @param  list<class-string<BaseEtlJob>>  $jobClasses
     * @return list<BaseEtlJob>
     */
    private function buildStage(array $jobClasses, EtlJobReporter $reporter): array
    {
        $jobs = [];

        foreach ($jobClasses as $class) {
            $slug = $class::slug();

            if (! $this->options->shouldRun($slug)) {
                continue;
            }

            $importJob = $reporter->start($slug);

            if (
                $this->options->resume
                && $importJob->status->isTerminal()
                && $importJob->status !== ImportJobStatus::Failed
            ) {
                continue;
            }

            $jobs[] = new $class((int) $importJob->id);
        }

        return $jobs;
    }

    private function onFailure(int $orchestratorJobId): Closure
    {
        return function () use ($orchestratorJobId): void {
            ImportJob::query()->where('id', $orchestratorJobId)->update([
                'status' => ImportJobStatus::Failed,
                'finished_at' => Carbon::now(),
            ]);
            $this->emitSecurityEvent('symfony_etl_failed', $orchestratorJobId);
        };
    }

    private function finishCallback(int $orchestratorJobId, EtlRunOptions $options): Closure
    {
        return function () use ($orchestratorJobId, $options): void {
            ImportJob::query()->where('id', $orchestratorJobId)->update([
                'status' => ImportJobStatus::Succeeded,
                'progress' => 100,
                'payload' => ['options' => [
                    'confirm' => $options->confirm,
                    'resume' => $options->resume,
                    'only' => $options->only,
                ]],
                'finished_at' => Carbon::now(),
            ]);
            $this->emitSecurityEvent('symfony_etl_completed', $orchestratorJobId);
        };
    }

    private function emitSecurityEvent(string $event, int $importJobId): void
    {
        if (! Schema::hasTable('security_events')) {
            return;
        }

        DB::table('security_events')->insert([
            'event' => $event,
            'reason' => sprintf('Symfony ETL orchestrator emitted %s.', $event),
            'affected_count' => null,
            'metadata' => json_encode([
                'import_job_id' => $importJobId,
            ], JSON_UNESCAPED_UNICODE),
            'occurred_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);
    }
}
