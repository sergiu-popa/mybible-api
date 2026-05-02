<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notes') || ! Schema::hasColumn('notes', 'book')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table): void {
            $table->string('book', 8)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notes') || ! Schema::hasColumn('notes', 'book')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table): void {
            $table->string('book', 3)->change();
        });
    }
};
