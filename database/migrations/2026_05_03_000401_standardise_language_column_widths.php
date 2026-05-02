<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unify `language` column widths across the schema to `CHAR(2)`. Runs
 * after the legacy 3-char codes have been backfilled so the change is
 * non-truncating. Per AC §11, columns retain nullability semantics they
 * already had — `users.language` stays nullable for cutover compat
 * (MBA-032 cleanup will drop the column entirely).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->resize('users', 'language', nullable: true);
        $this->resize('bible_versions', 'language', nullable: false);
        $this->resize('resource_categories', 'language', nullable: false);
        $this->resize('olympiad_questions', 'language', nullable: false);
    }

    public function down(): void
    {
        $this->expand('users', 'language', nullable: true);
        $this->expand('bible_versions', 'language', nullable: false);
        $this->expand('resource_categories', 'language', nullable: false);
        $this->expand('olympiad_questions', 'language', nullable: false);
    }

    private function resize(string $table, string $column, bool $nullable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $nullable): void {
            $col = $blueprint->char($column, 2);

            if ($nullable) {
                $col->nullable();
            }

            $col->change();
        });
    }

    private function expand(string $table, string $column, bool $nullable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $nullable): void {
            $col = $blueprint->string($column, 8);

            if ($nullable) {
                $col->nullable();
            }

            $col->change();
        });
    }
};
