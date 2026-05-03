<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('olympiad_questions')) {
            Schema::table('olympiad_questions', function (Blueprint $table): void {
                if (! Schema::hasColumn('olympiad_questions', 'uuid')) {
                    $table->char('uuid', 36)->nullable()->after('id');
                }
                if (! Schema::hasColumn('olympiad_questions', 'verse')) {
                    $table->unsignedSmallInteger('verse')->nullable()->after('chapters_to');
                }
                if (! Schema::hasColumn('olympiad_questions', 'chapter')) {
                    $table->unsignedSmallInteger('chapter')->nullable()->after('verse');
                }
                if (! Schema::hasColumn('olympiad_questions', 'is_reviewed')) {
                    $table->boolean('is_reviewed')->default(false)->after('explanation');
                }
            });

            // Backfill UUIDs
            DB::table('olympiad_questions')->whereNull('uuid')->orderBy('id')->lazyById(500)->each(function ($row): void {
                DB::table('olympiad_questions')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            });

            $orphanedUuid = DB::table('olympiad_questions')->whereNull('uuid')->count();
            if ($orphanedUuid === 0) {
                Schema::table('olympiad_questions', function (Blueprint $table): void {
                    $table->char('uuid', 36)->nullable(false)->change();
                });

                $hasUuidUnique = collect(Schema::getIndexes('olympiad_questions'))
                    ->contains(fn (array $idx): bool => ($idx['unique'] ?? false) && $idx['columns'] === ['uuid']);

                if (! $hasUuidUnique) {
                    Schema::table('olympiad_questions', function (Blueprint $table): void {
                        $table->unique('uuid');
                    });
                }
            }

            // Make chapters_from / chapters_to nullable to support chapter-only mode.
            Schema::table('olympiad_questions', function (Blueprint $table): void {
                $table->unsignedSmallInteger('chapters_from')->nullable()->change();
                $table->unsignedSmallInteger('chapters_to')->nullable()->change();
            });

            $hasReviewedIdx = collect(Schema::getIndexes('olympiad_questions'))
                ->contains(fn (array $idx): bool => $idx['columns'] === ['is_reviewed']);

            if (! $hasReviewedIdx) {
                Schema::table('olympiad_questions', function (Blueprint $table): void {
                    $table->index('is_reviewed');
                });
            }
        }

        if (Schema::hasTable('olympiad_answers')) {
            Schema::table('olympiad_answers', function (Blueprint $table): void {
                if (! Schema::hasColumn('olympiad_answers', 'uuid')) {
                    $table->char('uuid', 36)->nullable()->after('id');
                }
            });

            DB::table('olympiad_answers')->whereNull('uuid')->orderBy('id')->lazyById(500)->each(function ($row): void {
                DB::table('olympiad_answers')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            });

            $orphanedUuid = DB::table('olympiad_answers')->whereNull('uuid')->count();
            if ($orphanedUuid === 0) {
                Schema::table('olympiad_answers', function (Blueprint $table): void {
                    $table->char('uuid', 36)->nullable(false)->change();
                });

                $hasUuidUnique = collect(Schema::getIndexes('olympiad_answers'))
                    ->contains(fn (array $idx): bool => ($idx['unique'] ?? false) && $idx['columns'] === ['uuid']);

                if (! $hasUuidUnique) {
                    Schema::table('olympiad_answers', function (Blueprint $table): void {
                        $table->unique('uuid');
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // No-op: irreversible.
    }
};
