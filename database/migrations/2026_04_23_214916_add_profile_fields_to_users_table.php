<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `preferred_version` and soft-delete `deleted_at` columns to the
     * shared `users` table. Each column is guarded with a `hasColumn` check
     * so this migration is safe to re-run against the shared Symfony
     * database, where previous columns are already in place.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'preferred_version')) {
                $table->string('preferred_version', 16)->nullable()->after('language');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('users', 'preferred_version')) {
                $table->dropColumn('preferred_version');
            }
        });
    }
};
