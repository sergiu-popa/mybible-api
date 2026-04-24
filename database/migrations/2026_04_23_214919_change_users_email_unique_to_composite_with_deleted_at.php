<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Swap the single-column `users_email_unique` index for a composite
     * unique index on `(email, deleted_at)`. MySQL treats NULL values as
     * distinct in a unique index, so multiple soft-deleted rows sharing
     * the same email may coexist while only one live row per email is
     * allowed. This lets a user re-register after their account has been
     * soft-deleted.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        if ($this->hasIndex('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_email_unique');
            });
        }

        if (! $this->hasIndex('users', 'users_email_deleted_at_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['email', 'deleted_at'], 'users_email_deleted_at_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if ($this->hasIndex('users', 'users_email_deleted_at_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_email_deleted_at_unique');
            });
        }

        if (! $this->hasIndex('users', 'users_email_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('email', 'users_email_unique');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $rows = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics'
            . ' WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index],
        );

        return $rows !== null && (int) $rows->total > 0;
    }
};
