<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\SabbathSchool\Migrations;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RelaxFavoritesSegmentUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sentinel_zero_rows_are_migrated_to_null(): void
    {
        $user = User::factory()->create();
        $lesson = SabbathSchoolLesson::factory()->create();

        $rowId = DB::table('sabbath_school_favorites')->insertGetId([
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runMigration();

        $stored = DB::table('sabbath_school_favorites')
            ->where('id', $rowId)
            ->value('sabbath_school_segment_id');

        $this->assertNull($stored);
    }

    public function test_whole_lesson_and_per_segment_favorites_coexist_for_same_user_and_lesson(): void
    {
        $user = User::factory()->create();
        $lesson = SabbathSchoolLesson::factory()->create();
        $segment = SabbathSchoolSegment::factory()->forLesson($lesson)->create();

        $this->runMigration();

        $wholeLessonId = DB::table('sabbath_school_favorites')->insertGetId([
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $perSegmentId = DB::table('sabbath_school_favorites')->insertGetId([
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => $segment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotSame($wholeLessonId, $perSegmentId);
        $this->assertSame(2, DB::table('sabbath_school_favorites')
            ->where('user_id', $user->id)
            ->where('sabbath_school_lesson_id', $lesson->id)
            ->count());
    }

    public function test_duplicate_whole_lesson_favorite_is_rejected_by_functional_unique(): void
    {
        $user = User::factory()->create();
        $lesson = SabbathSchoolLesson::factory()->create();

        $this->runMigration();

        DB::table('sabbath_school_favorites')->insert([
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('sabbath_school_favorites')->insert([
            'user_id' => $user->id,
            'sabbath_school_lesson_id' => $lesson->id,
            'sabbath_school_segment_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_05_03_002007_relax_sabbath_school_favorites_segment_uniqueness.php',
        );
        $migration->up();
    }
}
