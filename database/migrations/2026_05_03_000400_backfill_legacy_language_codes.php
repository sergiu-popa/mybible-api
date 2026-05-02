<?php

declare(strict_types=1);

use App\Domain\Migration\Actions\BackfillLegacyLanguageCodesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Walk every column carrying a Symfony `varchar(3)` language code and
 * rewrite to ISO-639-1. Defaults unknown codes to `'ro'` and logs a
 * `security_events` row per defaulted value. Must run *before* the
 * width standardisation migration so the `CHAR(2)` change does not
 * truncate live values.
 */
return new class extends Migration
{
    public function up(): void
    {
        $action = new BackfillLegacyLanguageCodesAction;

        $targets = [
            ['users', 'language'],
            ['bible_versions', 'language'],
            ['resource_categories', 'language'],
            ['olympiad_questions', 'language'],
        ];

        foreach ($targets as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $action->handle($table, $column);
        }
    }

    public function down(): void
    {
        // Backfill is one-way: 2-char → 3-char would have to guess
        // (e.g. `de` → `deu` vs `ger`) and is not part of any rollback
        // we'd actually run. CI/dev rollback re-creates fresh tables
        // via the create migrations, so this is a documented no-op.
    }
};
