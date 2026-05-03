<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `sabbath_school_segments` to the Symfony-parity shape:
 *  - rename `lesson_id` → `sabbath_school_lesson_id` for the post-rename
 *    Symfony shape; in fresh-create the column is already named.
 *  - add `for_date` (Symfony `sb_section.for_date`); backfill from
 *    `lesson.date_from + day days` for non-null `day` rows.
 *  - relax `day` to nullable and ensure the `passages` JSON column exists
 *    so post-rename production gets the back-compat field.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sabbath_school_segments')) {
            return;
        }

        ReconcileTableHelper::renameColumnIfPresent('sabbath_school_segments', 'lesson_id', 'sabbath_school_lesson_id');

        if (! Schema::hasColumn('sabbath_school_segments', 'for_date')) {
            Schema::table('sabbath_school_segments', function (Blueprint $table): void {
                $table->date('for_date')->nullable()->after('sabbath_school_lesson_id');
            });
        }

        if (! Schema::hasColumn('sabbath_school_segments', 'day')) {
            Schema::table('sabbath_school_segments', function (Blueprint $table): void {
                $table->unsignedTinyInteger('day')->nullable();
            });
        } else {
            Schema::table('sabbath_school_segments', function (Blueprint $table): void {
                $table->unsignedTinyInteger('day')->nullable()->change();
            });
        }

        if (! Schema::hasColumn('sabbath_school_segments', 'passages')) {
            Schema::table('sabbath_school_segments', function (Blueprint $table): void {
                $table->json('passages')->nullable();
            });
        }

        $this->backfillForDate();
    }

    public function down(): void
    {
        if (! Schema::hasTable('sabbath_school_segments')) {
            return;
        }

        Schema::table('sabbath_school_segments', function (Blueprint $table): void {
            if (Schema::hasColumn('sabbath_school_segments', 'for_date')) {
                $table->dropColumn('for_date');
            }
        });
    }

    /**
     * Compute `for_date = lesson.date_from + day days` for every segment
     * whose `for_date` is null but `day` is set. Skips rows where the
     * lesson has no `date_from` to avoid surprise nulls in the seed data.
     */
    private function backfillForDate(): void
    {
        if (! Schema::hasColumn('sabbath_school_segments', 'for_date') ||
            ! Schema::hasColumn('sabbath_school_segments', 'day')) {
            return;
        }

        DB::statement(
            'UPDATE sabbath_school_segments s
             INNER JOIN sabbath_school_lessons l ON l.id = s.sabbath_school_lesson_id
             SET s.for_date = DATE_ADD(l.date_from, INTERVAL s.day DAY)
             WHERE s.for_date IS NULL AND s.day IS NOT NULL AND l.date_from IS NOT NULL',
        );
    }
};
