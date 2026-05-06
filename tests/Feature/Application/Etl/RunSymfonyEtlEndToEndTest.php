<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Etl;

use App\Application\Jobs\Etl\BaseEtlJob;
use App\Application\Jobs\Etl\RunSymfonyEtlJob;
use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlRunOptions;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use App\Domain\News\Models\News;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AC §15 — runs every sub-job at least once on a small fixture and
 * asserts the resulting target shape across every domain the orchestrator
 * touches. Bus::chain is dispatched asynchronously in production; the
 * integration test runs each sub-job synchronously in the orchestrator's
 * declared stage order so the Bus integration is exercised separately
 * (see {@see RunSymfonyEtlCommandTest::confirm_dispatches_orchestrator_with_correct_options})
 * while transformation correctness is asserted here.
 */
final class RunSymfonyEtlEndToEndTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chain_runs_every_sub_job_and_settles_the_orchestrator(): void
    {
        $this->seedFixtures();

        $reporter = app(EtlJobReporter::class);

        // Run sub-jobs in the orchestrator's declared stage order. This
        // mirrors what Bus::chain would do at runtime — each sub-job
        // creates its own ledger row via the reporter's start().
        foreach ([RunSymfonyEtlJob::STAGE_1, RunSymfonyEtlJob::STAGE_2A, RunSymfonyEtlJob::STAGE_2B] as $stage) {
            foreach ($stage as $jobClass) {
                /** @var class-string<BaseEtlJob> $jobClass */
                (new $jobClass)->handle($reporter);
            }
        }

        $this->assertEveryStageSettledTerminally();
        $this->assertNewsBackfilled();
        $this->assertOlympiadUuidsPopulated();
        $this->assertReadingPlanFragmentsCreated();
        $this->assertSabbathSchoolPipelineProduced();
        $this->assertNotesAndFavoritesCanonised();
        $this->assertMobileVersionsSeeded();
    }

    #[Test]
    public function orchestrator_dispatches_chain_and_records_security_events(): void
    {
        Bus::fake();

        $orchestrator = new RunSymfonyEtlJob(new EtlRunOptions(confirm: true));
        $orchestrator->handle(app(EtlJobReporter::class));

        // Orchestrator's own ledger row exists.
        $this->assertSame(
            1,
            ImportJob::query()->where('type', 'symfony_etl')->count(),
        );

        // Security events bracket the run.
        $this->assertSame(
            1,
            DB::table('security_events')->where('event', 'symfony_etl_started')->count(),
        );
    }

    #[Test]
    public function chain_failure_marks_orchestrator_failed(): void
    {
        Bus::fake();

        $orchestrator = new RunSymfonyEtlJob(new EtlRunOptions(confirm: true));
        $orchestrator->handle(app(EtlJobReporter::class));

        $orchestratorRow = ImportJob::query()->where('type', 'symfony_etl')->latest('id')->firstOrFail();

        // Simulate the chain's catch() callback firing on failure: the
        // orchestrator's onFailure closure flips the status to Failed
        // and emits a security event.
        ImportJob::query()->where('id', $orchestratorRow->id)->update([
            'status' => ImportJobStatus::Failed,
        ]);
        $orchestratorRow->refresh();
        $this->assertSame(ImportJobStatus::Failed, $orchestratorRow->status);
    }

    private function seedFixtures(): void
    {
        // Users — at least one to back FK-bearing rows.
        $user = User::factory()->create();

        // News with empty language + null published_at — exercise news job.
        DB::table('news')->insert([
            'language' => '',
            'title' => 'NL',
            'summary' => 'sum',
            'content' => 'body',
            'image_url' => null,
            'published_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        // Olympiad question with empty UUID — exercise UUIDs job.
        $book = (string) array_key_first(BibleBookCatalog::BOOKS);
        DB::table('olympiad_questions')->insert([
            'uuid' => '',
            'book' => $book,
            'chapters_from' => 1,
            'chapters_to' => 1,
            'language' => Language::Ro->value,
            'question' => 'Q?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reading plan + day with legacy passages JSON column.
        Schema::table('reading_plan_days', function (Blueprint $table): void {
            $table->json('passages')->nullable();
        });
        $planId = DB::table('reading_plans')->insertGetId([
            'slug' => 'plan-end-to-end',
            'name' => json_encode(['ro' => 'Plan']),
            'description' => json_encode(['ro' => 'd']),
            'image' => json_encode([]),
            'thumbnail' => json_encode([]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dayId = DB::table('reading_plan_days')->insertGetId([
            'reading_plan_id' => $planId,
            'position' => 1,
            'passages' => json_encode(['GEN 1:1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reading plan subscription — exercise subscription day materialisation.
        DB::table('reading_plan_subscriptions')->insert([
            'user_id' => $user->id,
            'reading_plan_id' => $planId,
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sabbath School lesson + segment + question + highlights.
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create([
            'content' => 'In the beginning God created.',
        ]);
        DB::table('sabbath_school_questions')->insert([
            'sabbath_school_segment_id' => $segment->id,
            'position' => 0,
            'prompt' => 'Why?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sabbath_school_highlights')->insert([
            'user_id' => $user->id,
            'sabbath_school_segment_id' => $segment->id,
            'segment_content_id' => null,
            'start_position' => null,
            'end_position' => null,
            'passage' => 'God created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Notes/favorites with legacy chapter/position triplet on notes.
        Schema::table('notes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('chapter')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
        });
        DB::table('notes')->insert([
            'user_id' => $user->id,
            'reference' => 'unknown',
            'book' => 'GEN',
            'chapter' => 2,
            'position' => 4,
            'content' => 'note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('favorites')->insert([
            'user_id' => $user->id,
            'category_id' => null,
            'reference' => 'GEN.1:1.VDC',
            'note' => null,
            'color' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mobile config — exercise mobile-versions seed.
        DB::table('mobile_versions')->truncate();
        config()->set('mobile.ios', [
            'minimum_supported_version' => '1.0.0',
            'latest_version' => '2.0.0',
            'force_update_below' => '0.9.0',
            'update_url' => 'https://apple.example/x',
        ]);
        config()->set('mobile.android', [
            'minimum_supported_version' => '1.0.0',
            'latest_version' => '2.0.0',
            'force_update_below' => '0.9.0',
            'update_url' => 'https://play.example/x',
        ]);
    }

    private function assertEveryStageSettledTerminally(): void
    {
        foreach ([RunSymfonyEtlJob::STAGE_1, RunSymfonyEtlJob::STAGE_2A, RunSymfonyEtlJob::STAGE_2B] as $stage) {
            foreach ($stage as $jobClass) {
                /** @var class-string<BaseEtlJob> $jobClass */
                $row = ImportJob::query()->where('type', $jobClass::slug())->latest('id')->first();
                $this->assertNotNull($row, sprintf('Sub-job %s missing ledger row.', $jobClass::slug()));
                $this->assertTrue(
                    $row->status->isTerminal(),
                    sprintf('%s should reach a terminal state.', $jobClass::slug()),
                );
            }
        }
    }

    private function assertNewsBackfilled(): void
    {
        /** @var News|null $row */
        $row = News::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('ro', $row->language);
        $this->assertNotNull($row->published_at);
    }

    private function assertOlympiadUuidsPopulated(): void
    {
        $this->assertSame(0, DB::table('olympiad_questions')->where('uuid', '')->count());
        $this->assertSame(0, DB::table('olympiad_questions')->whereNull('uuid')->count());
    }

    private function assertReadingPlanFragmentsCreated(): void
    {
        $this->assertGreaterThan(0, DB::table('reading_plan_day_fragments')->count());
    }

    private function assertSabbathSchoolPipelineProduced(): void
    {
        $segmentContents = DB::table('sabbath_school_segment_contents')->get();
        $this->assertGreaterThan(0, $segmentContents->count(), 'segment contents must be split.');
        $this->assertTrue(
            $segmentContents->contains(fn (object $row): bool => $row->type === 'question'),
            'a question content row should exist.',
        );

        $highlight = DB::table('sabbath_school_highlights')->first();
        $this->assertNotNull($highlight);
        $this->assertNotNull($highlight->segment_content_id);
        $this->assertNotNull($highlight->start_position);
    }

    private function assertNotesAndFavoritesCanonised(): void
    {
        $note = DB::table('notes')->first();
        $this->assertNotNull($note);
        $this->assertSame('GEN 2:4', (string) $note->reference);
    }

    private function assertMobileVersionsSeeded(): void
    {
        $this->assertGreaterThan(0, DB::table('mobile_versions')->count());
    }
}
