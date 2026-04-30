<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an integer `position` column on both `resource_categories` and
     * `educational_resources` so the admin can drag-and-drop reorder them
     * inside their parent (categories standalone, resources within a
     * category).
     *
     * Public read endpoints keep their existing ordering: categories lose
     * their previous `orderBy('id')` in favour of `position`; resources
     * within a category continue to surface newest-first by
     * `published_at`. The admin uses `position` directly via the new
     * reorder endpoints.
     *
     * Backfill seeds `position` based on the current implicit order
     * (`id ASC` for categories, `position-then-id` per category for
     * resources).
     */
    public function up(): void
    {
        if (Schema::hasTable('resource_categories') && ! Schema::hasColumn('resource_categories', 'position')) {
            Schema::table('resource_categories', function (Blueprint $table): void {
                $table->unsignedInteger('position')->default(0)->after('language');
                $table->index(['language', 'position'], 'resource_categories_language_position_idx');
            });

            $this->backfillPositions('resource_categories', null);
        }

        if (Schema::hasTable('educational_resources') && ! Schema::hasColumn('educational_resources', 'position')) {
            Schema::table('educational_resources', function (Blueprint $table): void {
                $table->unsignedInteger('position')->default(0)->after('resource_category_id');
                $table->index(
                    ['resource_category_id', 'position'],
                    'edu_res_category_position_idx',
                );
            });

            $this->backfillPositions('educational_resources', 'resource_category_id');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('educational_resources') && Schema::hasColumn('educational_resources', 'position')) {
            Schema::table('educational_resources', function (Blueprint $table): void {
                $table->dropIndex('edu_res_category_position_idx');
                $table->dropColumn('position');
            });
        }

        if (Schema::hasTable('resource_categories') && Schema::hasColumn('resource_categories', 'position')) {
            Schema::table('resource_categories', function (Blueprint $table): void {
                $table->dropIndex('resource_categories_language_position_idx');
                $table->dropColumn('position');
            });
        }
    }

    private function backfillPositions(string $table, ?string $groupColumn): void
    {
        $query = DB::table($table)->select(['id'])->orderBy('id');

        if ($groupColumn !== null) {
            $query->addSelect($groupColumn);
        }

        $rows = $query->get();
        $cursor = [];

        foreach ($rows as $row) {
            $bucket = $groupColumn !== null ? (string) $row->{$groupColumn} : '_all';
            $cursor[$bucket] = ($cursor[$bucket] ?? 0) + 1;

            DB::table($table)
                ->where('id', $row->id)
                ->update(['position' => $cursor[$bucket]]);
        }
    }
};
