<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notes') && ! Schema::hasColumn('notes', 'color')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->string('color', 9)->nullable()->after('content');
            });
        }

        if (Schema::hasTable('favorites') && ! Schema::hasColumn('favorites', 'color')) {
            Schema::table('favorites', function (Blueprint $table): void {
                $table->string('color', 9)->nullable()->after('note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notes') && Schema::hasColumn('notes', 'color')) {
            Schema::table('notes', function (Blueprint $table): void {
                $table->dropColumn('color');
            });
        }

        if (Schema::hasTable('favorites') && Schema::hasColumn('favorites', 'color')) {
            Schema::table('favorites', function (Blueprint $table): void {
                $table->dropColumn('color');
            });
        }
    }
};
