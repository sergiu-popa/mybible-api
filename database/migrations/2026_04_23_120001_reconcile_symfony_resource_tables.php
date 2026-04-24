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
        // Only exercised at MBA-020 cutover against the shared prod DB where
        // Symfony left `resource` / `resource_category` tables. Fresh CI/dev
        // databases already reach the target schema via the sibling
        // create_resource_categories_and_educational_resources_table migration
        // and skip this entirely.
        if (! Schema::hasTable('resource')) {
            return;
        }

        // Rename to snake_case plural names.
        if (Schema::hasTable('resource_category') && ! Schema::hasTable('resource_categories')) {
            Schema::rename('resource_category', 'resource_categories');
        }

        if (! Schema::hasTable('educational_resources')) {
            Schema::rename('resource', 'educational_resources');
        }

        // Align missing Laravel-convention columns on resource_categories.
        Schema::table('resource_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('resource_categories', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // Align columns on educational_resources.
        Schema::table('educational_resources', function (Blueprint $table): void {
            if (! Schema::hasColumn('educational_resources', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }

            if (! Schema::hasColumn('educational_resources', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        // Backfill uuids for any row that lacks one.
        $rows = DB::table('educational_resources')
            ->whereNull('uuid')
            ->orWhere('uuid', '')
            ->pluck('id');

        foreach ($rows as $id) {
            DB::table('educational_resources')
                ->where('id', $id)
                ->update(['uuid' => (string) Str::uuid()]);
        }

        // Lock the uuid column down now that all rows carry a value.
        Schema::table('educational_resources', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();

            // Ensure the unique index is in place (no-op if already present).
            try {
                $table->unique('uuid', 'educational_resources_uuid_unique');
            } catch (Throwable) {
                // Index already exists from the Symfony schema — ignore.
            }

            try {
                $table->index(
                    ['resource_category_id', 'type', 'published_at'],
                    'edu_res_cat_type_published_idx',
                );
            } catch (Throwable) {
                // Index already exists — ignore.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('educational_resources')) {
            return;
        }

        // Reverse the renames only. Do not attempt to restore Symfony-only
        // columns that were dropped: the create-migration's down() handles
        // full teardown in CI/dev, and prod rollback is out of scope.
        Schema::rename('educational_resources', 'resource');

        if (Schema::hasTable('resource_categories')) {
            Schema::rename('resource_categories', 'resource_category');
        }
    }
};
