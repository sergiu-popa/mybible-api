<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an optional 2-char `language` column to `daily_verse`. NULL is
     * treated as "global / all languages" — that matches legacy behaviour
     * and avoids fanning every existing row out across every supported
     * locale during backfill.
     *
     * The unique constraint on `for_date` stays in place for now: we are
     * not yet committing to a per-language daily verse, only making it
     * possible to record one when the product decision lands. If we move
     * to per-language down the line, the constraint flips to
     * `(for_date, language)` in a follow-up migration.
     */
    public function up(): void
    {
        if (! Schema::hasTable('daily_verse')) {
            return;
        }

        if (! Schema::hasColumn('daily_verse', 'language')) {
            Schema::table('daily_verse', function (Blueprint $table): void {
                $table->char('language', 2)->nullable()->after('for_date');
                $table->index('language', 'daily_verse_language_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('daily_verse')) {
            return;
        }

        if (Schema::hasColumn('daily_verse', 'language')) {
            Schema::table('daily_verse', function (Blueprint $table): void {
                $table->dropIndex('daily_verse_language_idx');
                $table->dropColumn('language');
            });
        }
    }
};
