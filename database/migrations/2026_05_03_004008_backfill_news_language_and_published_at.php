<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news')) {
            return;
        }

        if (Schema::hasColumn('news', 'language')) {
            DB::statement("UPDATE news SET language = 'ro' WHERE language IS NULL OR language = ''");
        }

        if (Schema::hasColumn('news', 'published_at')) {
            DB::statement('UPDATE news SET published_at = created_at WHERE published_at IS NULL');
        }
    }

    public function down(): void
    {
        // No-op: irreversible defensive backfill.
    }
};
