<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\SabbathSchool\Migrations;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EvolveSabbathSchoolSegmentsForForDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_for_date_from_lesson_date_from_plus_day(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create([
            'date_from' => '2026-01-04',
            'date_to' => '2026-01-10',
        ]);

        $segmentId = DB::table('sabbath_school_segments')->insertGetId([
            'sabbath_school_lesson_id' => $lesson->id,
            'day' => 3,
            'for_date' => null,
            'title' => 'Wednesday',
            'content' => '<p>body</p>',
            'passages' => json_encode(['GEN.1:1.VDC']),
            'position' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();

        $stored = DB::table('sabbath_school_segments')->where('id', $segmentId)->value('for_date');

        $this->assertSame('2026-01-07', $this->normalizeDate($stored));
    }

    public function test_it_leaves_for_date_null_when_day_is_null(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create([
            'date_from' => '2026-01-04',
            'date_to' => '2026-01-10',
        ]);

        $segmentId = DB::table('sabbath_school_segments')->insertGetId([
            'sabbath_school_lesson_id' => $lesson->id,
            'day' => null,
            'for_date' => null,
            'title' => 'Intro',
            'content' => '<p>body</p>',
            'passages' => json_encode([]),
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();

        $stored = DB::table('sabbath_school_segments')->where('id', $segmentId)->value('for_date');

        $this->assertNull($stored);
    }

    public function test_rerun_does_not_overwrite_existing_for_date(): void
    {
        $lesson = SabbathSchoolLesson::factory()->create([
            'date_from' => '2026-01-04',
            'date_to' => '2026-01-10',
        ]);

        $segmentId = DB::table('sabbath_school_segments')->insertGetId([
            'sabbath_school_lesson_id' => $lesson->id,
            'day' => 3,
            'for_date' => '2026-12-25',
            'title' => 'Christmas override',
            'content' => '<p>body</p>',
            'passages' => json_encode([]),
            'position' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();
        $this->runMigration();

        $stored = DB::table('sabbath_school_segments')->where('id', $segmentId)->value('for_date');

        $this->assertSame('2026-12-25', $this->normalizeDate($stored));
    }

    private function runMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_05_03_002002_evolve_sabbath_school_segments_for_for_date.php',
        );
        $migration->up();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
