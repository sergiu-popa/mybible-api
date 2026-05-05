<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the AI workflow columns to `commentary_texts`:
 * - `original` / `plain` / `with_references` HTML triple
 * - `errors_reported` denormalised counter
 * - per-pass `ai_corrected_*` and `ai_referenced_*` stamps
 *
 * Existing `content` is retained as the canonical "render this" fallback
 * when no AI passes have run yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commentary_texts')) {
            return;
        }

        Schema::table('commentary_texts', function (Blueprint $table): void {
            if (! Schema::hasColumn('commentary_texts', 'original')) {
                $table->longText('original')->nullable()->after('content');
            }
            if (! Schema::hasColumn('commentary_texts', 'plain')) {
                $table->longText('plain')->nullable()->after('original');
            }
            if (! Schema::hasColumn('commentary_texts', 'with_references')) {
                $table->longText('with_references')->nullable()->after('plain');
            }
            if (! Schema::hasColumn('commentary_texts', 'errors_reported')) {
                $table->unsignedInteger('errors_reported')->default(0)->after('with_references');
            }
            if (! Schema::hasColumn('commentary_texts', 'ai_corrected_at')) {
                $table->timestamp('ai_corrected_at')->nullable()->after('errors_reported');
            }
            if (! Schema::hasColumn('commentary_texts', 'ai_corrected_prompt_version')) {
                $table->string('ai_corrected_prompt_version', 64)->nullable()->after('ai_corrected_at');
            }
            if (! Schema::hasColumn('commentary_texts', 'ai_referenced_at')) {
                $table->timestamp('ai_referenced_at')->nullable()->after('ai_corrected_prompt_version');
            }
            if (! Schema::hasColumn('commentary_texts', 'ai_referenced_prompt_version')) {
                $table->string('ai_referenced_prompt_version', 64)->nullable()->after('ai_referenced_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commentary_texts')) {
            return;
        }

        Schema::table('commentary_texts', function (Blueprint $table): void {
            $columns = [
                'original',
                'plain',
                'with_references',
                'errors_reported',
                'ai_corrected_at',
                'ai_corrected_prompt_version',
                'ai_referenced_at',
                'ai_referenced_prompt_version',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('commentary_texts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
