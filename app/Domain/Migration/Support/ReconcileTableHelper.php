<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared helpers for reconcile migrations. Each Symfony→Laravel rename
 * follows the same pattern: drop the empty Laravel-shape table that the
 * create migration may have laid down, then rename the legacy table in
 * place. Centralised here so the per-domain migrations stay readable.
 */
final class ReconcileTableHelper
{
    /**
     * Rename `$legacy` to `$target`, after dropping `$target` if it was
     * created empty by a sibling create migration. Returns whether a
     * rename actually happened.
     */
    public static function rename(string $legacy, string $target): bool
    {
        if (! Schema::hasTable($legacy)) {
            return false;
        }

        if (Schema::hasTable($target) && DB::table($target)->count() === 0) {
            Schema::drop($target);
        }

        if (Schema::hasTable($target)) {
            return false;
        }

        Schema::rename($legacy, $target);

        return true;
    }

    public static function renameColumnIfPresent(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $from)) {
            return;
        }

        if (Schema::hasColumn($table, $to)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
            $blueprint->renameColumn($from, $to);
        });
    }

    public static function ensureTimestamps(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'created_at')) {
                $blueprint->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn($table, 'updated_at')) {
                $blueprint->timestamp('updated_at')->nullable();
            }
        });
    }
}
