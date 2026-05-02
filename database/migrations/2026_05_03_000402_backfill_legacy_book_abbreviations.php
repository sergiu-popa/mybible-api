<?php

declare(strict_types=1);

use App\Domain\Migration\Actions\BackfillLegacyBookAbbreviationsAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rewrite long-form book values (e.g. `Genesis`, `1 Corinteni`) into
 * USFM-3 (`GEN`, `1CO`) using `_legacy_book_abbreviation_map`. Throws
 * loudly on unmapped values per AC §14 — operator extends the map and
 * re-runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $action = new BackfillLegacyBookAbbreviationsAction;

        $targets = [
            ['olympiad_questions', 'book'],
            ['notes', 'book'],
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
        // No-op: long-form names cannot be deterministically reconstructed.
    }
};
