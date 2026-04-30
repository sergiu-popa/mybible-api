<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add `ui_locale` (the admin's UI language preference, 2-char) and
     * `is_active` (admin enable/disable flag, separate from soft-delete) to
     * `users` so the admin shell's `/auth/me` payload can carry both fields.
     *
     * `is_active` defaults to `true`; flipping it to `false` is the
     * mechanism super-admins use to suspend an account without losing the
     * audit trail (deletion remains a separate action via `deleted_at`).
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'ui_locale')) {
                $table->char('ui_locale', 2)->nullable()->after('languages');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('ui_locale');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('users', 'ui_locale')) {
                $table->dropColumn('ui_locale');
            }
        });
    }
};
