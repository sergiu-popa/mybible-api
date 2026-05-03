<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relaxes `sabbath_school_favorites.sabbath_school_segment_id` to
 * NULL-able so the whole-lesson-favorite case stops needing the
 * sentinel `0`. Existing sentinel rows are migrated to NULL.
 *
 * MySQL 8 functional unique index over
 * `(user_id, sabbath_school_lesson_id, COALESCE(segment_id, 0))`
 * collapses NULL to 0 at index time only, so two rows with NULL
 * `segment_id` for the same `(user, lesson)` still collide while
 * column-level NULL semantics remain intact for application code.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sabbath_school_favorites')) {
            return;
        }

        Schema::table('sabbath_school_favorites', function (Blueprint $table): void {
            $table->unsignedBigInteger('sabbath_school_segment_id')->nullable()->default(null)->change();
        });

        DB::table('sabbath_school_favorites')
            ->where('sabbath_school_segment_id', 0)
            ->update(['sabbath_school_segment_id' => null]);

        if ($this->hasIndex('sabbath_school_favorites_user_lesson_segment_unique')) {
            Schema::table('sabbath_school_favorites', function (Blueprint $table): void {
                $table->dropUnique('sabbath_school_favorites_user_lesson_segment_unique');
            });
        }

        if (! $this->hasIndex('sabbath_school_favorites_user_lesson_seg_func_unique')) {
            DB::statement(
                'CREATE UNIQUE INDEX sabbath_school_favorites_user_lesson_seg_func_unique
                 ON sabbath_school_favorites (user_id, sabbath_school_lesson_id, (COALESCE(sabbath_school_segment_id, 0)))',
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sabbath_school_favorites')) {
            return;
        }

        if ($this->hasIndex('sabbath_school_favorites_user_lesson_seg_func_unique')) {
            DB::statement('DROP INDEX sabbath_school_favorites_user_lesson_seg_func_unique ON sabbath_school_favorites');
        }
    }

    private function hasIndex(string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND index_name = ? LIMIT 1',
            [$database, $name],
        ) !== null;
    }
};
