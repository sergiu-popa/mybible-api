<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an integer `position` column on `olympiad_questions` so the
     * admin can drag-and-drop reorder questions inside a theme tuple
     * `(language, book, chapters_from, chapters_to)`.
     *
     * Backfills `position` per theme using the existing `id ASC` order
     * so the legacy listing remains stable. Public endpoints sort the
     * canonical question set by `(position, id)` before applying the
     * seed-driven shuffle, so admin reorders are honored as the natural
     * ordering of each theme.
     */
    public function up(): void
    {
        if (! Schema::hasTable('olympiad_questions')) {
            return;
        }

        if (! Schema::hasColumn('olympiad_questions', 'position')) {
            Schema::table('olympiad_questions', function (Blueprint $table): void {
                $table->unsignedInteger('position')->default(0)->after('chapters_to');
                $table->index(
                    ['language', 'book', 'chapters_from', 'chapters_to', 'position'],
                    'olympiad_questions_theme_position_idx',
                );
            });

            $rows = DB::table('olympiad_questions')
                ->select(['id', 'language', 'book', 'chapters_from', 'chapters_to'])
                ->orderBy('id')
                ->get();

            $cursor = [];

            foreach ($rows as $row) {
                $bucket = sprintf(
                    '%s|%s|%d|%d',
                    (string) $row->language,
                    (string) $row->book,
                    (int) $row->chapters_from,
                    (int) $row->chapters_to,
                );

                $cursor[$bucket] = ($cursor[$bucket] ?? 0) + 1;

                DB::table('olympiad_questions')
                    ->where('id', $row->id)
                    ->update(['position' => $cursor[$bucket]]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('olympiad_questions') || ! Schema::hasColumn('olympiad_questions', 'position')) {
            return;
        }

        Schema::table('olympiad_questions', function (Blueprint $table): void {
            $table->dropIndex('olympiad_questions_theme_position_idx');
            $table->dropColumn('position');
        });
    }
};
