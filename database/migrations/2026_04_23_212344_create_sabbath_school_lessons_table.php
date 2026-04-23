<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sabbath_school_lessons')) {
            return;
        }

        Schema::create('sabbath_school_lessons', function (Blueprint $table): void {
            $table->id();
            $table->char('language', 2);
            $table->string('title');
            $table->date('week_start');
            $table->date('week_end');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Catalog listing hits (language, published_at) — newest first.
            $table->index(['language', 'published_at'], 'sabbath_school_lessons_language_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_lessons');
    }
};
