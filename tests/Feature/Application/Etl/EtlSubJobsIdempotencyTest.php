<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Etl;

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
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AC §9 — every ETL sub-job is idempotent at the protocol level: running
 * it twice never produces a different terminal status, and re-running on
 * an already-`Succeeded` ledger row short-circuits without doing more
 * work. Source tables are not present in the test schema (Symfony source
 * data is not seeded into CI), so each sub-job's defensive
 * `Schema::hasTable` guard makes the body a no-op — but the protocol
 * assertion (start → handle → terminal status, twice) is meaningful
 * regardless.
 *
 * Per-sub-job behavioural assertions (target shape, error routing) live
 * in dedicated fixture-driven tests under this same namespace.
 */
final class EtlSubJobsIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{class-string<BaseEtlJob>}>
     */
    public static function subJobProvider(): iterable
    {
        $classes = [
            BackfillLanguageCodesJob::class,
            BackfillBookCodesJob::class,
            EtlBibleBooksAndVersesJob::class,
            EtlHymnalStanzasJob::class,
            EtlReadingPlansJob::class,
            EtlReadingPlanSubscriptionsJob::class,
            EtlSabbathSchoolContentJob::class,
            EtlSabbathSchoolQuestionsJob::class,
            EtlSabbathSchoolHighlightsJob::class,
            EtlDevotionalTypesJob::class,
            EtlMobileVersionsSeedJob::class,
            EtlCollectionsParentJob::class,
            EtlOlympiadUuidsJob::class,
            EtlResourceDownloadsJob::class,
            EtlNewsLanguageDefaultJob::class,
            EtlNotesAndFavoritesJob::class,
            EtlUserPreferredLanguageJob::class,
        ];

        foreach ($classes as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @param  class-string<BaseEtlJob>  $jobClass
     */
    #[Test]
    #[DataProvider('subJobProvider')]
    public function sub_job_runs_to_a_terminal_state_and_is_idempotent(string $jobClass): void
    {
        $reporter = app(EtlJobReporter::class);

        // First pass — sub-job opens its own ImportJob row via the reporter.
        (new $jobClass)->handle($reporter);

        $first = ImportJob::query()
            ->where('type', $jobClass::slug())
            ->latest('id')
            ->first();

        $this->assertInstanceOf(ImportJob::class, $first);
        $this->assertTrue(
            $first->status->isTerminal(),
            sprintf('%s should reach a terminal state on first pass.', $jobClass::slug()),
        );

        $firstSucceeded = (int) ($first->payload['succeeded'] ?? 0);

        // Second pass — re-running should be a clean no-op: the reporter
        // surfaces the existing terminal row and the job short-circuits.
        (new $jobClass)->handle($reporter);

        $rowsForType = ImportJob::query()
            ->where('type', $jobClass::slug())
            ->count();

        $this->assertSame(
            1,
            $rowsForType,
            sprintf('%s must reuse its terminal row on re-run, not pile new ones up.', $jobClass::slug()),
        );

        $first->refresh();
        $this->assertSame(
            $firstSucceeded,
            (int) ($first->payload['succeeded'] ?? 0),
            sprintf('%s succeeded count should match across runs.', $jobClass::slug()),
        );
    }
}
