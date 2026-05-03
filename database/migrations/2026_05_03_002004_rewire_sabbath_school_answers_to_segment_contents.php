<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rewires `sabbath_school_answers` from question-keyed to
 * segment-content-keyed.
 *
 * The MBA-031 ETL backfills `segment_content_id` from the legacy
 * question rows; until then the column stays nullable and no FK is
 * re-introduced. MBA-032 flips NOT NULL and adds the FK after cutover.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sabbath_school_answers')) {
            return;
        }

        $this->dropLegacyForeignKeys();

        ReconcileTableHelper::renameColumnIfPresent('sabbath_school_answers', 'sabbath_school_question_id', 'segment_content_id');
        ReconcileTableHelper::renameColumnIfPresent('sabbath_school_answers', 'question_id', 'segment_content_id');

        if (Schema::hasColumn('sabbath_school_answers', 'segment_content_id')) {
            Schema::table('sabbath_school_answers', function (Blueprint $table): void {
                $table->unsignedBigInteger('segment_content_id')->nullable()->change();
            });
        }

        if ($this->hasIndex('sabbath_school_answers_user_question_unique')) {
            Schema::table('sabbath_school_answers', function (Blueprint $table): void {
                $table->dropUnique('sabbath_school_answers_user_question_unique');
            });
        }

        if (! $this->hasIndex('sabbath_school_answers_user_content_unique') &&
            Schema::hasColumn('sabbath_school_answers', 'segment_content_id')) {
            Schema::table('sabbath_school_answers', function (Blueprint $table): void {
                $table->unique(
                    ['user_id', 'segment_content_id'],
                    'sabbath_school_answers_user_content_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sabbath_school_answers')) {
            return;
        }

        if ($this->hasIndex('sabbath_school_answers_user_content_unique')) {
            Schema::table('sabbath_school_answers', function (Blueprint $table): void {
                $table->dropUnique('sabbath_school_answers_user_content_unique');
            });
        }
    }

    private function dropLegacyForeignKeys(): void
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $rows = $connection->select(
            'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
               AND COLUMN_NAME IN (?, ?)
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, 'sabbath_school_answers', 'sabbath_school_question_id', 'question_id'],
        );

        foreach ($rows as $row) {
            $name = (string) $row->name;
            Schema::table('sabbath_school_answers', function (Blueprint $table) use ($name): void {
                $table->dropForeign($name);
            });
        }
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
