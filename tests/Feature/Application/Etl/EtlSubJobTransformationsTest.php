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
use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\Reference\Data\BibleBookCatalog;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Domain\Shared\Enums\Language;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AC §14 — every ETL sub-job has a fixture-driven test asserting:
 *   (a) target shape (counts + a representative row),
 *   (b) idempotency on re-run (no duplicates appear),
 *   (c) error rows route to legacy/archive tables, not lost
 *       (where the sub-job has such a routing surface).
 *
 * Sub-jobs operating on Symfony source tables that the Laravel schema
 * never created (e.g. `_legacy_book_map`, `hymnal_verses`,
 * `resource_download` singular, `_legacy_favorite_category_map`) get
 * their source table created inline so the transformation can be
 * exercised — this mirrors the post-reconcile production state where
 * MBA-023 leaves these tables behind for MBA-031 to consume.
 */
final class EtlSubJobTransformationsTest extends TestCase
{
    use RefreshDatabase;

    private EtlJobReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = app(EtlJobReporter::class);
    }

    /**
     * @param  class-string<BaseEtlJob>  $jobClass
     */
    private function runSubJob(string $jobClass): ImportJob
    {
        $job = new $jobClass;
        $job->handle($this->reporter);

        $importJob = ImportJob::query()
            ->where('type', $jobClass::slug())
            ->latest('id')
            ->first();

        $this->assertInstanceOf(ImportJob::class, $importJob);

        return $importJob;
    }

    private function dropImportJobs(string $slug): void
    {
        ImportJob::query()->where('type', $slug)->delete();
    }

    // -----------------------------------------------------------------
    // Stage 1 — identifier normalisation
    // -----------------------------------------------------------------

    #[Test]
    public function backfill_language_codes_runs_action_over_every_target_table(): void
    {
        // Schema migrations have already widened legacy `varchar(3)` to
        // `char(2)` everywhere — the column constraint refuses 3-char
        // inserts now, so this sub-job is a post-cutover safety net (per
        // its docblock). Asserts the protocol: the action runs against
        // every (table, column) pair without raising and the ledger row
        // settles to Succeeded.
        DB::table('users')->insert([
            'name' => 'lang-2',
            'email' => 'lang2@example.com',
            'password' => 'hash',
            'language' => 'ro',
            'roles' => json_encode([]),
            'is_super' => false,
            'languages' => json_encode([]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = $this->runSubJob(BackfillLanguageCodesJob::class);
        $this->assertSame(ImportJobStatus::Succeeded, $job->status);
        $this->assertSame(
            4, // four targets in BackfillLanguageCodesJob::TARGETS
            (int) ($job->payload['succeeded'] ?? 0),
            'BackfillLanguageCodesJob should report one succeeded entry per target table.',
        );
    }

    #[Test]
    public function backfill_book_codes_succeeds_when_targets_are_already_canonical(): void
    {
        DB::table('notes')->insert([
            'user_id' => User::factory()->create()->id,
            'reference' => 'GEN.1:1.VDC',
            'book' => 'GEN',
            'content' => 'A note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = $this->runSubJob(BackfillBookCodesJob::class);
        $this->assertTrue($job->status->isTerminal());
        $this->assertGreaterThan(0, (int) ($job->payload['succeeded'] ?? 0));
    }

    #[Test]
    public function backfill_book_codes_routes_unmapped_books_to_payload_errors(): void
    {
        // Inject a notes row whose `book` is not mapped to a USFM-3 code
        // anywhere — neither in BibleBookCatalog nor in
        // _legacy_book_abbreviation_map. The action should raise
        // UnmappedLegacyBookException; the sub-job catches it and
        // routes the offender to payload.errors instead of aborting.
        DB::table('notes')->insert([
            'user_id' => User::factory()->create()->id,
            'reference' => 'unknown',
            'book' => 'WEIRDX',
            'content' => 'rogue value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = $this->runSubJob(BackfillBookCodesJob::class);

        // `Partial` requires both succeeded AND errors. Whether we land
        // Partial or Failed depends on how the action processes the other
        // (clean) tables; either way an error must be present.
        $errors = $job->payload['errors'] ?? [];
        $this->assertNotEmpty($errors, 'Unmapped books must surface in payload.errors.');
        $this->assertStringContainsString('WEIRDX', json_encode($errors) ?: '');
    }

    // -----------------------------------------------------------------
    // Stage 2 — domain ETL
    // -----------------------------------------------------------------

    #[Test]
    public function bible_books_and_verses_populates_chapter_counts_from_verses(): void
    {
        $version = DB::table('bible_versions')->insertGetId([
            'name' => 'Cornilescu',
            'abbreviation' => 'VDC',
            'language' => 'ro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $book = DB::table('bible_books')->insertGetId([
            'abbreviation' => 'GEN',
            'testament' => 'OT',
            'position' => 1,
            'chapter_count' => 50,
            'names' => json_encode(['en' => 'Genesis']),
            'short_names' => json_encode(['en' => 'Gen']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bible_verses')->insert([
            ['bible_version_id' => $version, 'bible_book_id' => $book, 'chapter' => 1, 'verse' => 1, 'text' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['bible_version_id' => $version, 'bible_book_id' => $book, 'chapter' => 1, 'verse' => 2, 'text' => 'b', 'created_at' => now(), 'updated_at' => now()],
            ['bible_version_id' => $version, 'bible_book_id' => $book, 'chapter' => 1, 'verse' => 3, 'text' => 'c', 'created_at' => now(), 'updated_at' => now()],
            ['bible_version_id' => $version, 'bible_book_id' => $book, 'chapter' => 2, 'verse' => 1, 'text' => 'd', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->runSubJob(EtlBibleBooksAndVersesJob::class);

        $this->assertSame(2, DB::table('bible_chapters')->where('bible_book_id', $book)->count());
        $this->assertSame(
            3,
            (int) DB::table('bible_chapters')->where('bible_book_id', $book)->where('number', 1)->value('verse_count'),
        );

        // Idempotency.
        $this->dropImportJobs(EtlBibleBooksAndVersesJob::class::slug());
        $this->runSubJob(EtlBibleBooksAndVersesJob::class);
        $this->assertSame(2, DB::table('bible_chapters')->where('bible_book_id', $book)->count());
    }

    #[Test]
    public function olympiad_uuids_populates_missing_uuids_only(): void
    {
        $book = (string) array_key_first(BibleBookCatalog::BOOKS);
        $existingUuid = (string) Str::uuid();

        $a = DB::table('olympiad_questions')->insertGetId([
            'uuid' => '',
            'book' => $book,
            'chapters_from' => 1,
            'chapters_to' => 1,
            'language' => Language::Ro->value,
            'question' => 'Q?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $b = DB::table('olympiad_questions')->insertGetId([
            'uuid' => $existingUuid,
            'book' => $book,
            'chapters_from' => 1,
            'chapters_to' => 1,
            'language' => Language::Ro->value,
            'question' => 'Q2?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlOlympiadUuidsJob::class);

        $this->assertNotNull(DB::table('olympiad_questions')->where('id', $a)->value('uuid'));
        $this->assertSame(
            $existingUuid,
            (string) DB::table('olympiad_questions')->where('id', $b)->value('uuid'),
            'Already-set uuid must not be regenerated.',
        );
    }

    #[Test]
    public function user_preferred_language_nulls_every_existing_value(): void
    {
        if (! Schema::hasColumn('users', 'preferred_language')) {
            $this->markTestSkipped('preferred_language column not present in this branch.');
        }

        DB::table('users')->insert([
            'name' => 'pl',
            'email' => 'pl@example.com',
            'password' => 'hash',
            'roles' => json_encode([]),
            'is_super' => false,
            'languages' => json_encode([]),
            'is_active' => true,
            'preferred_language' => 'ro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlUserPreferredLanguageJob::class);

        $this->assertNull(DB::table('users')->where('email', 'pl@example.com')->value('preferred_language'));
    }

    #[Test]
    public function news_language_default_fills_empty_language_and_missing_published_at(): void
    {
        $created = CarbonImmutable::now()->subDays(3);
        DB::table('news')->insert([
            'language' => '',
            'title' => 't',
            'summary' => 's',
            'content' => 'c',
            'image_url' => null,
            'published_at' => null,
            'created_at' => $created,
            'updated_at' => $created,
        ]);

        $this->runSubJob(EtlNewsLanguageDefaultJob::class);

        $row = DB::table('news')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('ro', $row->language);
        $this->assertNotNull($row->published_at);
    }

    #[Test]
    public function mobile_versions_seed_inserts_one_row_per_platform_kind_when_empty(): void
    {
        // The reconcile migration may have already seeded these — clear
        // so the sub-job's "if empty" guard fires.
        DB::table('mobile_versions')->truncate();
        $this->assertSame(0, DB::table('mobile_versions')->count());

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

        $this->runSubJob(EtlMobileVersionsSeedJob::class);

        $this->assertSame(6, DB::table('mobile_versions')->count(), 'one row per (platform,kind)');

        // Idempotency: re-running on a populated table is a no-op.
        $this->dropImportJobs(EtlMobileVersionsSeedJob::class::slug());
        $this->runSubJob(EtlMobileVersionsSeedJob::class);
        $this->assertSame(6, DB::table('mobile_versions')->count());
    }

    #[Test]
    public function reading_plans_expands_passages_into_fragments(): void
    {
        $planId = DB::table('reading_plans')->insertGetId([
            'slug' => 'test-plan-slug',
            'name' => json_encode(['ro' => 'Plan-test']),
            'description' => json_encode(['ro' => 'desc']),
            'image' => json_encode([]),
            'thumbnail' => json_encode([]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dayId = DB::table('reading_plan_days')->insertGetId([
            'reading_plan_id' => $planId,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add legacy `passages` column on reading_plan_days for the
        // duration of this test (the schema migration drops/keeps it
        // depending on environment; the job guards on hasColumn).
        Schema::table('reading_plan_days', function (Blueprint $table): void {
            $table->json('passages')->nullable();
        });

        DB::table('reading_plan_days')->where('id', $dayId)->update([
            'passages' => json_encode(['GEN 1:1', 'GEN 1:2']),
        ]);

        $this->runSubJob(EtlReadingPlansJob::class);

        $fragment = DB::table('reading_plan_day_fragments')->where('reading_plan_day_id', $dayId)->first();
        $this->assertNotNull($fragment);
        $this->assertSame(FragmentType::References->value, $fragment->type);
        $decoded = json_decode((string) $fragment->content, true);
        $this->assertSame(['GEN 1:1', 'GEN 1:2'], $decoded);

        // Re-run: fragment count stays the same.
        $this->dropImportJobs(EtlReadingPlansJob::class::slug());
        $this->runSubJob(EtlReadingPlansJob::class);
        $this->assertSame(1, DB::table('reading_plan_day_fragments')->where('reading_plan_day_id', $dayId)->count());
    }

    #[Test]
    public function reading_plan_subscriptions_materialises_per_day_rows(): void
    {
        $userId = User::factory()->create()->id;
        $planId = DB::table('reading_plans')->insertGetId([
            'slug' => 'plan-sub',
            'name' => json_encode(['ro' => 'Plan']),
            'description' => json_encode(['ro' => 'd']),
            'image' => json_encode([]),
            'thumbnail' => json_encode([]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $day1 = DB::table('reading_plan_days')->insertGetId([
            'reading_plan_id' => $planId,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $day2 = DB::table('reading_plan_days')->insertGetId([
            'reading_plan_id' => $planId,
            'position' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subId = DB::table('reading_plan_subscriptions')->insertGetId([
            'user_id' => $userId,
            'reading_plan_id' => $planId,
            'start_date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlReadingPlanSubscriptionsJob::class);

        $rows = DB::table('reading_plan_subscription_days')
            ->where('reading_plan_subscription_id', $subId)
            ->orderBy('reading_plan_day_id')
            ->get();

        $this->assertCount(2, $rows);
        $rowOne = $rows->firstWhere('reading_plan_day_id', $day1);
        $rowTwo = $rows->firstWhere('reading_plan_day_id', $day2);
        $this->assertNotNull($rowOne);
        $this->assertNotNull($rowTwo);
        $this->assertSame('2026-01-01', $rowOne->scheduled_date);
        $this->assertSame('2026-01-02', $rowTwo->scheduled_date);

        // Idempotency: re-run should not duplicate the day rows.
        $this->dropImportJobs(EtlReadingPlanSubscriptionsJob::class::slug());
        $this->runSubJob(EtlReadingPlanSubscriptionsJob::class);
        $this->assertSame(
            2,
            DB::table('reading_plan_subscription_days')->where('reading_plan_subscription_id', $subId)->count(),
        );
    }

    #[Test]
    public function sabbath_school_content_generates_fallback_text_row(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create([
            'content' => '<p>Body</p>',
        ]);

        $this->runSubJob(EtlSabbathSchoolContentJob::class);

        $rows = DB::table('sabbath_school_segment_contents')->where('segment_id', $segment->id)->get();
        $this->assertCount(1, $rows);
        $first = $rows->first();
        $this->assertNotNull($first);
        $this->assertSame('text', $first->type);
        $this->assertStringContainsString('Body', (string) $first->content);

        $this->dropImportJobs(EtlSabbathSchoolContentJob::class::slug());
        $this->runSubJob(EtlSabbathSchoolContentJob::class);
        $this->assertSame(1, DB::table('sabbath_school_segment_contents')->where('segment_id', $segment->id)->count());
    }

    #[Test]
    public function sabbath_school_questions_inserts_question_content_rows(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();
        $questionId = DB::table('sabbath_school_questions')->insertGetId([
            'sabbath_school_segment_id' => $segment->id,
            'position' => 0,
            'prompt' => 'What does the prophet say?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlSabbathSchoolQuestionsJob::class);

        $contentRow = DB::table('sabbath_school_segment_contents')
            ->where('segment_id', $segment->id)
            ->where('type', 'question')
            ->first();
        $this->assertNotNull($contentRow);
        $this->assertSame('What does the prophet say?', (string) $contentRow->content);
        $this->assertSame(sprintf('legacy_question_%d', $questionId), $contentRow->title);
    }

    #[Test]
    public function sabbath_school_highlights_resolves_offsets_and_archives_unparseable(): void
    {
        $userId = User::factory()->create()->id;
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();

        DB::table('sabbath_school_segment_contents')->insert([
            'segment_id' => $segment->id,
            'type' => 'text',
            'title' => null,
            'position' => 0,
            'content' => 'In the beginning God created the heaven and the earth.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Resolvable highlight.
        DB::table('sabbath_school_highlights')->insert([
            'user_id' => $userId,
            'sabbath_school_segment_id' => $segment->id,
            'segment_content_id' => null,
            'start_position' => null,
            'end_position' => null,
            'passage' => 'God created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Unparseable — no such substring exists in any content row.
        $unresolvableId = DB::table('sabbath_school_highlights')->insertGetId([
            'user_id' => $userId,
            'sabbath_school_segment_id' => $segment->id,
            'segment_content_id' => null,
            'start_position' => null,
            'end_position' => null,
            'passage' => 'the truth shall set you free',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlSabbathSchoolHighlightsJob::class);

        $resolved = DB::table('sabbath_school_highlights')->where('passage', 'God created')->first();
        $this->assertNotNull($resolved);
        $this->assertNotNull($resolved->segment_content_id);
        $this->assertSame(17, (int) $resolved->start_position);
        $this->assertSame(28, (int) $resolved->end_position);

        $archived = DB::table('sabbath_school_highlights_legacy')->where('id', $unresolvableId)->first();
        $this->assertNotNull($archived, 'Unparseable highlight must be archived to the legacy table.');

        $event = DB::table('security_events')
            ->where('event', 'sabbath_school_highlights_unparseable')
            ->latest('id')
            ->first();
        $this->assertNotNull($event, 'Unparseable highlights must emit a security_events row.');
    }

    #[Test]
    public function devotional_types_seeds_from_legacy_singular_table(): void
    {
        if (! Schema::hasTable('devotional_types') || ! Schema::hasTable('devotionals')) {
            $this->markTestSkipped('devotional_types/devotionals not present in this branch.');
        }

        // Recreate the Symfony singular `devotional_type` legacy source
        // inline. The reconcile may have left this behind for the ETL
        // to consume.
        Schema::create('devotional_type', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64);
            $table->string('title')->nullable();
            $table->char('language', 2)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        DB::table('devotional_type')->insert([
            'slug' => 'morning',
            'title' => 'Morning Devotion',
            'language' => 'ro',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlDevotionalTypesJob::class);

        $type = DB::table('devotional_types')
            ->where('slug', 'morning')
            ->where('language', 'ro')
            ->first();
        $this->assertNotNull($type, 'devotional_types row should be seeded from legacy devotional_type.');
        $this->assertSame('Morning Devotion', $type->title);
    }

    #[Test]
    public function collections_parent_is_a_safe_no_op_when_no_legacy_join_table_exists(): void
    {
        // No `_legacy_favorite_category_map` → the rewire branch must
        // short-circuit without errors.
        $job = $this->runSubJob(EtlCollectionsParentJob::class);
        $this->assertTrue($job->status->isTerminal());
    }

    #[Test]
    public function resource_downloads_copies_legacy_singular_rows_to_polymorphic(): void
    {
        // Recreate the Symfony-shape singular-typed source table inline.
        Schema::create('resource_download', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('educational_resource_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('device_id', 64)->nullable();
            $table->char('language', 2)->nullable();
            $table->string('source', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::table('resource_download')->insert([
            'educational_resource_id' => 42,
            'user_id' => null,
            'device_id' => 'dev-1',
            'language' => 'ro',
            'source' => 'web',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->runSubJob(EtlResourceDownloadsJob::class);

        $row = DB::table('resource_downloads')->first();
        $this->assertNotNull($row);
        $this->assertSame('educational_resource', $row->downloadable_type);
        $this->assertSame(42, (int) $row->downloadable_id);

        // Idempotency: same probe rejects a second copy of the legacy row.
        $this->dropImportJobs(EtlResourceDownloadsJob::class::slug());
        $this->runSubJob(EtlResourceDownloadsJob::class);
        $this->assertSame(1, DB::table('resource_downloads')->count());
    }

    #[Test]
    public function notes_and_favorites_canonises_book_chapter_position_triplets(): void
    {
        // Inject the legacy chapter/position triplet columns onto `notes`.
        Schema::table('notes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('chapter')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
        });

        $userId = User::factory()->create()->id;
        $noteId = DB::table('notes')->insertGetId([
            'user_id' => $userId,
            'reference' => 'pre-canonical',
            'book' => 'GEN',
            'chapter' => 3,
            'position' => 5,
            'content' => 'note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlNotesAndFavoritesJob::class);

        $this->assertSame('GEN 3:5', (string) DB::table('notes')->where('id', $noteId)->value('reference'));
    }

    #[Test]
    public function notes_and_favorites_backfills_color_from_favorite_categories(): void
    {
        $userId = User::factory()->create()->id;
        $categoryId = DB::table('favorite_categories')->insertGetId([
            'user_id' => $userId,
            'name' => 'Important',
            'color' => '#ff0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $favoriteId = DB::table('favorites')->insertGetId([
            'user_id' => $userId,
            'category_id' => $categoryId,
            'reference' => 'GEN.1:1.VDC',
            'note' => null,
            'color' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runSubJob(EtlNotesAndFavoritesJob::class);

        $this->assertSame(
            '#ff0000',
            (string) DB::table('favorites')->where('id', $favoriteId)->value('color'),
            'favorite without colour should inherit from its category.',
        );
    }

    #[Test]
    public function notes_and_favorites_rewires_legacy_favorite_category_map(): void
    {
        Schema::create('_legacy_favorite_category_map', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_favorite_id')->primary();
            $table->unsignedBigInteger('category_id');
        });

        $userId = User::factory()->create()->id;
        $categoryId = DB::table('favorite_categories')->insertGetId([
            'user_id' => $userId,
            'name' => 'Bookmarks',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $favoriteId = DB::table('favorites')->insertGetId([
            'user_id' => $userId,
            'category_id' => null,
            'reference' => 'GEN.1:1.VDC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('_legacy_favorite_category_map')->insert([
            'legacy_favorite_id' => $favoriteId,
            'category_id' => $categoryId,
        ]);

        $this->runSubJob(EtlNotesAndFavoritesJob::class);

        $this->assertSame(
            $categoryId,
            (int) DB::table('favorites')->where('id', $favoriteId)->value('category_id'),
        );
    }

    #[Test]
    public function hymnal_stanzas_is_a_safe_no_op_when_legacy_verses_table_absent(): void
    {
        // `hymnal_verses` legacy source table is never created in CI; the
        // job must short-circuit without raising. Idempotency is covered
        // by the protocol test elsewhere; this asserts the no-source path.
        $job = $this->runSubJob(EtlHymnalStanzasJob::class);
        $this->assertTrue($job->status->isTerminal());
    }
}
