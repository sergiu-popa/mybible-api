<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prod path: the Symfony `user` table exists and is reshaped by the
        // reconcile_symfony_user_table migration. Fresh/CI path: no `user`
        // table, so build the final target schema directly here.
        if (! Schema::hasTable('user') && ! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                // `increments('id')` preserves the Symfony `INT UNSIGNED AUTO_INCREMENT`
                // width so existing foreign keys in the shared DB keep aligning.
                $table->increments('id');
                $table->string('name', 50);
                // 180 matches the Symfony column length; dev/local seeds use
                // safeEmail() which fits well under this ceiling.
                $table->string('email', 180)->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->json('roles');
                $table->string('language', 3)->nullable();
                $table->string('avatar')->nullable();
                $table->timestamp('last_login')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};
