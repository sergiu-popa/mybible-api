<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `resource_downloads` to the Laravel-final polymorphic shape. In
 * a fresh environment the table does not exist; in production the
 * MBA-023 rename brought the legacy `resource_download` shape
 * (`resource_id`, `ip_address`, `created_at`) — this migration creates
 * the table or evolves the legacy one in place: renames `resource_id`
 * → `downloadable_id`, adds `downloadable_type` (backfilled to
 * `'educational_resource'`), `user_id`, `device_id`, `language`,
 * `source`, drops `ip_address`, and adds the three lookup indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_downloads')) {
            Schema::create('resource_downloads', function (Blueprint $table): void {
                $table->id();
                $table->string('downloadable_type', 64);
                $table->unsignedBigInteger('downloadable_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->string('device_id', 64)->nullable();
                $table->char('language', 2)->nullable();
                $table->string('source', 16)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(
                    ['downloadable_type', 'downloadable_id', 'created_at'],
                    'resource_downloads_target_created_idx',
                );
                $table->index(['user_id', 'created_at'], 'resource_downloads_user_created_idx');
                $table->index('created_at', 'resource_downloads_created_idx');
            });

            return;
        }

        ReconcileTableHelper::renameColumnIfPresent('resource_downloads', 'resource_id', 'downloadable_id');

        $this->ensureColumns();
        $this->backfillDownloadableType();
        $this->makeDownloadableTypeNotNull();
        $this->dropIpAddress();
        $this->ensureIndexes();
    }

    public function down(): void
    {
        if (! Schema::hasTable('resource_downloads')) {
            return;
        }

        Schema::table('resource_downloads', function (Blueprint $table): void {
            foreach ([
                'resource_downloads_target_created_idx',
                'resource_downloads_user_created_idx',
                'resource_downloads_created_idx',
            ] as $name) {
                if ($this->hasIndex($name)) {
                    $table->dropIndex($name);
                }
            }

            if (Schema::hasColumn('resource_downloads', 'user_id')) {
                $table->dropForeign(['user_id']);
            }

            foreach (['user_id', 'device_id', 'language', 'source', 'downloadable_type'] as $column) {
                if (Schema::hasColumn('resource_downloads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function ensureColumns(): void
    {
        Schema::table('resource_downloads', function (Blueprint $table): void {
            if (! Schema::hasColumn('resource_downloads', 'downloadable_type')) {
                $table->string('downloadable_type', 64)->nullable()->after('id');
            }

            if (! Schema::hasColumn('resource_downloads', 'user_id')) {
                $table->unsignedInteger('user_id')->nullable()->after('downloadable_id');
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('resource_downloads', 'device_id')) {
                $table->string('device_id', 64)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('resource_downloads', 'language')) {
                $table->char('language', 2)->nullable()->after('device_id');
            }

            if (! Schema::hasColumn('resource_downloads', 'source')) {
                $table->string('source', 16)->nullable()->after('language');
            }
        });
    }

    private function backfillDownloadableType(): void
    {
        if (! Schema::hasColumn('resource_downloads', 'downloadable_type')) {
            return;
        }

        DB::table('resource_downloads')
            ->whereNull('downloadable_type')
            ->update(['downloadable_type' => 'educational_resource']);
    }

    private function makeDownloadableTypeNotNull(): void
    {
        if (! Schema::hasColumn('resource_downloads', 'downloadable_type')) {
            return;
        }

        Schema::table('resource_downloads', function (Blueprint $table): void {
            $table->string('downloadable_type', 64)->nullable(false)->change();
        });
    }

    private function dropIpAddress(): void
    {
        if (! Schema::hasColumn('resource_downloads', 'ip_address')) {
            return;
        }

        Schema::table('resource_downloads', function (Blueprint $table): void {
            $table->dropColumn('ip_address');
        });
    }

    private function ensureIndexes(): void
    {
        Schema::table('resource_downloads', function (Blueprint $table): void {
            if (! $this->hasIndex('resource_downloads_target_created_idx')) {
                $table->index(
                    ['downloadable_type', 'downloadable_id', 'created_at'],
                    'resource_downloads_target_created_idx',
                );
            }

            if (! $this->hasIndex('resource_downloads_user_created_idx')) {
                $table->index(['user_id', 'created_at'], 'resource_downloads_user_created_idx');
            }

            if (! $this->hasIndex('resource_downloads_created_idx')) {
                $table->index('created_at', 'resource_downloads_created_idx');
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
