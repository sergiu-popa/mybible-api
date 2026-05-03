<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evolves `sabbath_school_lessons` to the Symfony-parity shape:
 *  - rename Symfony↔Laravel column pairs (`week_start`/`week_end` ↔
 *    `date_from`/`date_to`) so both fresh-create and post-rename
 *    production end up with `date_from`/`date_to`.
 *  - add the trimester FK + Symfony metadata fields (`age_group`,
 *    `memory_verse`, `image_cdn_url`, `number`).
 *  - re-assert the Symfony `(language, age_group, trimester_id,
 *    date_from, date_to)` UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sabbath_school_lessons')) {
            return;
        }

        ReconcileTableHelper::renameColumnIfPresent('sabbath_school_lessons', 'week_start', 'date_from');
        ReconcileTableHelper::renameColumnIfPresent('sabbath_school_lessons', 'week_end', 'date_to');

        if (! Schema::hasColumn('sabbath_school_lessons', 'date_from')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->date('date_from')->nullable();
            });
        }

        if (! Schema::hasColumn('sabbath_school_lessons', 'date_to')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->date('date_to')->nullable();
            });
        }

        if (! Schema::hasColumn('sabbath_school_lessons', 'trimester_id')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->foreignId('trimester_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('sabbath_school_trimesters')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('sabbath_school_lessons', 'age_group')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->string('age_group', 50)->nullable()->after('language');
            });

            DB::table('sabbath_school_lessons')
                ->whereNull('age_group')
                ->update(['age_group' => 'adult']);

            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->string('age_group', 50)->nullable(false)->change();
            });
        }

        if (! Schema::hasColumn('sabbath_school_lessons', 'memory_verse')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->text('memory_verse')->nullable();
            });
        }

        if (! Schema::hasColumn('sabbath_school_lessons', 'image_cdn_url')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->text('image_cdn_url')->nullable();
            });
        }

        if (! Schema::hasColumn('sabbath_school_lessons', 'number')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->unsignedSmallInteger('number')->nullable()->after('age_group');
            });

            $this->backfillLessonNumbers();

            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->unsignedSmallInteger('number')->nullable(false)->change();
            });
        }

        if (! $this->hasIndex('sabbath_school_lessons', 'sabbath_school_lessons_lesson_unique')) {
            Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
                $table->unique(
                    ['language', 'age_group', 'trimester_id', 'date_from', 'date_to'],
                    'sabbath_school_lessons_lesson_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sabbath_school_lessons')) {
            return;
        }

        Schema::table('sabbath_school_lessons', function (Blueprint $table): void {
            if ($this->hasIndex('sabbath_school_lessons', 'sabbath_school_lessons_lesson_unique')) {
                $table->dropUnique('sabbath_school_lessons_lesson_unique');
            }

            foreach (['number', 'image_cdn_url', 'memory_verse', 'age_group'] as $column) {
                if (Schema::hasColumn('sabbath_school_lessons', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('sabbath_school_lessons', 'trimester_id')) {
                $table->dropConstrainedForeignId('trimester_id');
            }
        });
    }

    /**
     * Deterministic backfill via row-number per `(language, age_group,
     * trimester_id, year(date_from))`, ordered by `date_from, id`. Tie-
     * breaker on `id` keeps reruns stable when two legacy lessons share
     * the same `date_from`.
     */
    private function backfillLessonNumbers(): void
    {
        $rows = DB::table('sabbath_school_lessons')
            ->select(['id', 'language', 'age_group', 'trimester_id', 'date_from'])
            ->orderBy('language')
            ->orderBy('age_group')
            ->orderByRaw('IFNULL(trimester_id, 0)')
            ->orderByRaw('IFNULL(YEAR(date_from), 0)')
            ->orderBy('date_from')
            ->orderBy('id')
            ->get();

        $counters = [];

        foreach ($rows as $row) {
            $year = $row->date_from !== null ? substr((string) $row->date_from, 0, 4) : '0';
            $key = sprintf(
                '%s|%s|%s|%s',
                (string) $row->language,
                (string) $row->age_group,
                (string) ($row->trimester_id ?? '0'),
                $year,
            );

            $counters[$key] = ($counters[$key] ?? 0) + 1;

            DB::table('sabbath_school_lessons')
                ->where('id', $row->id)
                ->update(['number' => $counters[$key]]);
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $name],
        ) !== null;
    }
};
