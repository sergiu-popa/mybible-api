<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only runs against the shared prod DB where Symfony left its `user`
        // table. Fresh CI/dev databases already reach the target schema via
        // the initial create_users_table migration and skip this entirely.
        if (! Schema::hasTable('user')) {
            return;
        }

        Schema::rename('user', 'users');

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('lastLogin', 'last_login');
            $table->renameColumn('createdAt', 'created_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            // MySQL drops any single-column index automatically when the
            // column is dropped, so the Symfony `resetToken` unique index
            // goes with the column.
            $table->dropColumn(['salt', 'resetToken', 'resetDate']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'remember_token', 'updated_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('salt')->nullable();
            $table->string('resetToken')->nullable()->unique();
            $table->timestamp('resetDate')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('last_login', 'lastLogin');
            $table->renameColumn('created_at', 'createdAt');
        });

        Schema::rename('users', 'user');
    }
};
