<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lays down the Laravel-final shape for the commentary domain. In a fresh
 * environment this is the only migration that runs for these tables. In
 * production, the Symfony reconcile (2026_05_03_000202) drops these
 * empty tables and renames the legacy `commentary`/`commentary_text`
 * shape in; the evolution migrations that follow then bring the renamed
 * tables up to the Laravel-final shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commentaries')) {
            Schema::create('commentaries', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->json('name');
                $table->string('abbreviation', 32);
                $table->char('language', 2);
                $table->boolean('is_published')->default(false);
                $table->foreignId('source_commentary_id')
                    ->nullable()
                    ->constrained('commentaries')
                    ->nullOnDelete();
                $table->timestamps();

                $table->index(['language', 'is_published'], 'commentaries_language_published_idx');
            });
        }

        if (! Schema::hasTable('commentary_texts')) {
            Schema::create('commentary_texts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('commentary_id')
                    ->constrained('commentaries')
                    ->cascadeOnDelete();
                $table->string('book', 8);
                $table->unsignedSmallInteger('chapter');
                $table->unsignedSmallInteger('position');
                $table->unsignedSmallInteger('verse_from')->nullable();
                $table->unsignedSmallInteger('verse_to')->nullable();
                $table->string('verse_label', 20)->nullable();
                $table->longText('content');
                $table->timestamps();

                $table->unique(
                    ['commentary_id', 'book', 'chapter', 'position'],
                    'commentary_texts_unique_position',
                );
                $table->index(
                    ['commentary_id', 'book', 'chapter', 'verse_from', 'verse_to'],
                    'commentary_texts_verse_lookup_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commentary_texts');
        Schema::dropIfExists('commentaries');
    }
};
