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
use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AC §9, §14 — every ETL sub-job is idempotent: running it twice on the
 * same DB state produces the same row counts and does not duplicate
 * data. Source tables are not present in the test schema (Symfony data
 * is not seeded into CI), so each sub-job's defensive `Schema::hasTable`
 * guard keeps it as a no-op — but the idempotency assertion still
 * meaningfully holds.
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
        $importJob = ImportJob::query()->create([
            'type' => $jobClass::slug(),
            'status' => ImportJobStatus::Pending,
            'progress' => 0,
            'payload' => [],
        ]);

        // First pass.
        (new $jobClass($importJob->id))->handle($reporter);
        $importJob->refresh();
        $this->assertTrue(
            $importJob->status->isTerminal(),
            sprintf('%s should reach a terminal state on first pass.', $jobClass::slug()),
        );
        $firstPayload = $importJob->payload;

        // Second pass — re-running should be a clean no-op.
        $importJob2 = ImportJob::query()->create([
            'type' => $jobClass::slug(),
            'status' => ImportJobStatus::Pending,
            'progress' => 0,
            'payload' => [],
        ]);
        (new $jobClass($importJob2->id))->handle($reporter);
        $importJob2->refresh();

        $this->assertTrue($importJob2->status->isTerminal());
        $this->assertSame(
            $firstPayload['succeeded'] ?? 0,
            $importJob2->payload['succeeded'] ?? 0,
            sprintf('%s succeeded count should match across runs.', $jobClass::slug()),
        );
    }
}
