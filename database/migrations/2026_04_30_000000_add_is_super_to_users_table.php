<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the `is_super` boolean column that gates platform-wide admin
     * sections (Bible catalog, Mobile Versions, Admins management).
     *
     * Replaces the legacy "languages = all 6 means super-admin" heuristic.
     * Defaults to `false` for every existing row; the oldest user holding
     * the `admin` role is promoted to `is_super = true` so the team retains
     * a working super-admin account after the cutover.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_super')) {
                $table->boolean('is_super')->default(false)->after('roles');
            }
        });

        $oldestAdminId = DB::table('users')
            ->whereJsonContains('roles', 'admin')
            ->orderBy('id')
            ->value('id');

        if ($oldestAdminId !== null) {
            DB::table('users')
                ->where('id', $oldestAdminId)
                ->update(['is_super' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'is_super')) {
                $table->dropColumn('is_super');
            }
        });
    }
};
