<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row-per-language settings table. Each setting is a typed FK
 * (default Bible version, default commentary, default devotional type);
 * super-admins set the values via the admin UI. The frontend reads the
 * safe-to-publish slug-only projection on cold start.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('language_settings')) {
            return;
        }

        Schema::create('language_settings', function (Blueprint $table): void {
            $table->id();
            $table->char('language', 2)->unique();

            $table->unsignedBigInteger('default_bible_version_id')->nullable();
            $table->foreign('default_bible_version_id', 'language_settings_bible_version_foreign')
                ->references('id')->on('bible_versions')
                ->nullOnDelete();

            $table->unsignedBigInteger('default_commentary_id')->nullable();
            $table->foreign('default_commentary_id', 'language_settings_commentary_foreign')
                ->references('id')->on('commentaries')
                ->nullOnDelete();

            $table->unsignedBigInteger('default_devotional_type_id')->nullable();
            $table->foreign('default_devotional_type_id', 'language_settings_devotional_type_foreign')
                ->references('id')->on('devotional_types')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('language_settings');
    }
};
