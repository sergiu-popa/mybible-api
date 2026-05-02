<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop Doctrine ORM artefacts left behind by the Symfony app:
 * `doctrine_migration_versions` table and any leftover legacy auth
 * columns on `users` (`salt`, `reset_token`, `reset_date`). The user
 * reconcile migration in MBA-018 already handles the column drop on
 * the prod path; this migration is the idempotent safety net for the
 * environments where the user reconcile did not run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doctrine_migration_versions')) {
            Schema::drop('doctrine_migration_versions');
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['salt', 'reset_token', 'resetToken', 'reset_date', 'resetDate'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Doctrine artefacts are not restored — they have no business in
        // the Laravel runtime. CI/dev rollback is unaffected because the
        // create migrations never created them.
    }
};
