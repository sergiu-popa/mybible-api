<?php

declare(strict_types=1);

namespace App\Application\Commands;

use App\Application\Jobs\Etl\BackfillBookCodesJob;
use App\Application\Jobs\Etl\BackfillLanguageCodesJob;
use App\Application\Jobs\Etl\BaseEtlJob;
use App\Application\Jobs\Etl\EtlBibleBooksAndVersesJob;
use App\Application\Jobs\Etl\EtlCollectionsParentJob;
use App\Application\Jobs\Etl\EtlDevotionalTypesJob;
use App\Application\Jobs\Etl\EtlHymnalStanzasJob;
use App\Application\Jobs\Etl\EtlMobileVersionsSeedJob;
use App\Application\Jobs\Etl\EtlNewsLanguageDefaultJob;
use App\Application\Jobs\Etl\EtlNotesAndFavoritesJob;
use App\Application\Jobs\Etl\EtlOlympiadUuidsJob;
use App\Application\Jobs\Etl\EtlReadingPlansJob;
use App\Application\Jobs\Etl\EtlReadingPlanSubscriptionsJob;
use App\Application\Jobs\Etl\EtlResourceDownloadsJob;
use App\Application\Jobs\Etl\EtlSabbathSchoolContentJob;
use App\Application\Jobs\Etl\EtlSabbathSchoolHighlightsJob;
use App\Application\Jobs\Etl\EtlSabbathSchoolQuestionsJob;
use App\Application\Jobs\Etl\EtlUserPreferredLanguageJob;
use App\Application\Jobs\Etl\RunSymfonyEtlJob;
use App\Application\Support\DryRunRollback;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlRunOptions;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operator entry point for the Symfony→Laravel data ETL.
 *
 *   php artisan symfony:etl --confirm                 # full run
 *   php artisan symfony:etl --dry-run                 # rolled back, JSON summary on stdout
 *   php artisan symfony:etl --resume                  # only un-finished sub-jobs
 *   php artisan symfony:etl --only=etl_news_language  # restrict the chain
 *
 * `--confirm` is required for non-dry-run, non-resume executions to
 * defend against an accidental cutover-time press of return.
 */
final class RunSymfonyEtlCommand extends Command
{
    protected $signature = 'symfony:etl
        {--confirm : Acknowledge that this is a destructive cutover-time run.}
        {--dry-run : Wrap each sub-job in a transaction that is rolled back; emit a JSON summary on stdout.}
        {--resume : Skip sub-jobs whose import_jobs row is already in a non-failed terminal state.}
        {--only=* : Restrict the chain to the listed sub-job slugs.}';

    protected $description = 'Run the Symfony→Laravel data ETL via Horizon-backed Bus::chain orchestrator.';

    /** @var array<string, class-string<BaseEtlJob>> */
    private const SUB_JOB_REGISTRY = [
        'etl_backfill_language_codes' => BackfillLanguageCodesJob::class,
        'etl_backfill_book_codes' => BackfillBookCodesJob::class,
        'etl_bible_books_and_verses' => EtlBibleBooksAndVersesJob::class,
        'etl_hymnal_stanzas' => EtlHymnalStanzasJob::class,
        'etl_reading_plans' => EtlReadingPlansJob::class,
        'etl_reading_plan_subscriptions' => EtlReadingPlanSubscriptionsJob::class,
        'etl_sabbath_school_content' => EtlSabbathSchoolContentJob::class,
        'etl_sabbath_school_questions' => EtlSabbathSchoolQuestionsJob::class,
        'etl_sabbath_school_highlights' => EtlSabbathSchoolHighlightsJob::class,
        'etl_devotional_types' => EtlDevotionalTypesJob::class,
        'etl_mobile_versions_seed' => EtlMobileVersionsSeedJob::class,
        'etl_collections_parent' => EtlCollectionsParentJob::class,
        'etl_olympiad_uuids' => EtlOlympiadUuidsJob::class,
        'etl_resource_downloads' => EtlResourceDownloadsJob::class,
        'etl_news_language_default' => EtlNewsLanguageDefaultJob::class,
        'etl_notes_and_favorites' => EtlNotesAndFavoritesJob::class,
        'etl_user_preferred_language' => EtlUserPreferredLanguageJob::class,
    ];

    public function handle(EtlJobReporter $reporter): int
    {
        $options = $this->buildOptions();

        if (! $options->dryRun && ! $options->confirm && ! $options->resume) {
            $this->error('Refusing to run without --confirm. Pass --dry-run for a rehearsal or --confirm for a real cutover-time pass.');

            return self::FAILURE;
        }

        if ($options->dryRun) {
            return $this->runDryRun($reporter, $options);
        }

        // Real run — dispatch the orchestrator. Horizon picks it up off
        // the `etl` queue.
        RunSymfonyEtlJob::dispatch($options);

        $this->info('Dispatched RunSymfonyEtlJob onto the etl queue. Watch progress at /horizon and import_jobs.');

        return self::SUCCESS;
    }

    private function buildOptions(): EtlRunOptions
    {
        $only = (array) $this->option('only');
        /** @var list<string> $only */
        $only = array_values(array_filter(array_map(
            static fn ($slug): string => is_string($slug) ? trim($slug) : '',
            $only,
        ), static fn (string $slug): bool => $slug !== ''));

        return new EtlRunOptions(
            confirm: (bool) $this->option('confirm'),
            dryRun: (bool) $this->option('dry-run'),
            resume: (bool) $this->option('resume'),
            only: $only,
        );
    }

    private function runDryRun(EtlJobReporter $reporter, EtlRunOptions $options): int
    {
        $summary = [];

        foreach (self::SUB_JOB_REGISTRY as $slug => $class) {
            if (! $options->shouldRun($slug)) {
                continue;
            }

            $rolledBackResult = $this->executeInTransaction($class, $reporter);

            $summary[$slug] = $rolledBackResult;
        }

        $this->line((string) json_encode([
            'dry_run' => true,
            'sub_jobs' => $summary,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * @param  class-string<BaseEtlJob>  $class
     * @return array<string, mixed>
     */
    private function executeInTransaction(string $class, EtlJobReporter $reporter): array
    {
        $start = microtime(true);
        $thrown = null;
        $result = [];

        try {
            DB::transaction(function () use ($class, $reporter, &$result): void {
                /** @var BaseEtlJob $job */
                $job = new $class;
                $job->handle($reporter);

                // The job created its own ledger row inside this rolled-back
                // transaction; surface it so the dry-run summary reports the
                // rehearsed status before the rollback erases it.
                $importJob = ImportJob::query()
                    ->where('type', $class::slug())
                    ->latest('id')
                    ->first();

                if ($importJob instanceof ImportJob) {
                    $result['status'] = $importJob->status->value;
                    $result['payload'] = $importJob->payload;
                }

                throw new DryRunRollback;
            });
        } catch (DryRunRollback) {
            // Expected — transaction was rolled back to keep the DB pristine.
        } catch (Throwable $exception) {
            $thrown = $exception->getMessage();
        }

        $result['duration_ms'] = (int) ((microtime(true) - $start) * 1000);
        if ($thrown !== null) {
            $result['error'] = $thrown;
        }

        return $result;
    }
}
