<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renames `news.image_path` to `news.image_url` so the column name
     * matches the public field already exposed by `NewsResource` and
     * leaves room for the eventual S3 direct-upload pipeline (E-06) to
     * store fully-resolved URLs without another rename.
     *
     * The column continues to hold a relative storage path until E-06
     * lands; the resource keeps building the absolute URL via
     * `Storage::disk('public')->url($value)` for now.
     */
    public function up(): void
    {
        if (! Schema::hasTable('news')) {
            return;
        }

        if (Schema::hasColumn('news', 'image_path') && ! Schema::hasColumn('news', 'image_url')) {
            Schema::table('news', function (Blueprint $table): void {
                $table->renameColumn('image_path', 'image_url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('news')) {
            return;
        }

        if (Schema::hasColumn('news', 'image_url') && ! Schema::hasColumn('news', 'image_path')) {
            Schema::table('news', function (Blueprint $table): void {
                $table->renameColumn('image_url', 'image_path');
            });
        }
    }
};
