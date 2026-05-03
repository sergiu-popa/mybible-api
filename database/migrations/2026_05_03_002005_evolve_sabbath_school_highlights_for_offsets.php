<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reshapes `sabbath_school_highlights` from passage-string to
 * intra-text offsets keyed on `segment_content_id`.
 *
 * Per the rollout plan, the new columns are nullable through this
 * story so MBA-031 can ETL legacy `passage` rows without a flag day.
 * MBA-032 flips NOT NULL and drops `passage` after cutover.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sabbath_school_highlights')) {
            return;
        }

        if (! Schema::hasColumn('sabbath_school_highlights', 'segment_content_id')) {
            Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
                $table->foreignId('segment_content_id')
                    ->nullable()
                    ->after('sabbath_school_segment_id')
                    ->constrained('sabbath_school_segment_contents')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('sabbath_school_highlights', 'start_position')) {
            Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
                $table->unsignedInteger('start_position')->nullable();
            });
        }

        if (! Schema::hasColumn('sabbath_school_highlights', 'end_position')) {
            Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
                $table->unsignedInteger('end_position')->nullable();
            });
        }

        if (! Schema::hasColumn('sabbath_school_highlights', 'color')) {
            Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
                $table->string('color', 9)->nullable();
            });
        }

        if (Schema::hasColumn('sabbath_school_highlights', 'passage')) {
            Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
                $table->string('passage', 255)->nullable()->change();
            });
        }

        if (! $this->hasIndex('sabbath_school_highlights_user_content_range_unique')) {
            Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
                $table->unique(
                    ['user_id', 'segment_content_id', 'start_position', 'end_position', 'deleted_at'],
                    'sabbath_school_highlights_user_content_range_unique',
                );
            });
        }

        if (! $this->hasIndex('sabbath_school_highlights_segment_content_idx')) {
            Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
                $table->index('segment_content_id', 'sabbath_school_highlights_segment_content_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sabbath_school_highlights')) {
            return;
        }

        Schema::table('sabbath_school_highlights', function (Blueprint $table): void {
            if ($this->hasIndex('sabbath_school_highlights_user_content_range_unique')) {
                $table->dropUnique('sabbath_school_highlights_user_content_range_unique');
            }

            if ($this->hasIndex('sabbath_school_highlights_segment_content_idx')) {
                $table->dropIndex('sabbath_school_highlights_segment_content_idx');
            }

            foreach (['color', 'end_position', 'start_position'] as $column) {
                if (Schema::hasColumn('sabbath_school_highlights', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('sabbath_school_highlights', 'segment_content_id')) {
                $table->dropConstrainedForeignId('segment_content_id');
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND index_name = ? LIMIT 1',
            [$database, $name],
        ) !== null;
    }
};
