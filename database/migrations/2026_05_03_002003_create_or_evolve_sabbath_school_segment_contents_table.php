<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `sabbath_school_segment_contents` to the Symfony-parity shape:
 *  - in fresh-create the table does not exist; create it with the full
 *    Laravel-final schema.
 *  - in production the MBA-023 reconcile rename brought the legacy
 *    `sb_content` shape (Symfony col names: `section_id`); rename
 *    `section_id → segment_id` and add any missing columns + index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sabbath_school_segment_contents')) {
            Schema::create('sabbath_school_segment_contents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('segment_id')
                    ->constrained('sabbath_school_segments')
                    ->cascadeOnDelete();
                $table->string('type', 50);
                $table->string('title', 128)->nullable();
                $table->unsignedSmallInteger('position')->default(0);
                $table->longText('content');
                $table->timestamps();

                $table->index(['segment_id', 'position'], 'sabbath_school_segment_contents_segment_position_idx');
            });

            return;
        }

        ReconcileTableHelper::renameColumnIfPresent('sabbath_school_segment_contents', 'section_id', 'segment_id');

        Schema::table('sabbath_school_segment_contents', function (Blueprint $table): void {
            if (! Schema::hasColumn('sabbath_school_segment_contents', 'type')) {
                $table->string('type', 50);
            }

            if (! Schema::hasColumn('sabbath_school_segment_contents', 'title')) {
                $table->string('title', 128)->nullable();
            }

            if (! Schema::hasColumn('sabbath_school_segment_contents', 'position')) {
                $table->unsignedSmallInteger('position')->default(0);
            }

            if (! Schema::hasColumn('sabbath_school_segment_contents', 'content')) {
                $table->longText('content');
            }

            if (! Schema::hasColumn('sabbath_school_segment_contents', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('sabbath_school_segment_contents', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        if (! $this->hasIndex('sabbath_school_segment_contents_segment_position_idx')) {
            Schema::table('sabbath_school_segment_contents', function (Blueprint $table): void {
                $table->index(['segment_id', 'position'], 'sabbath_school_segment_contents_segment_position_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_segment_contents');
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
