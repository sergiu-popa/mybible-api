<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sabbath_school_segments')) {
            return;
        }

        Schema::create('sabbath_school_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sabbath_school_lesson_id')
                ->constrained('sabbath_school_lessons')
                ->cascadeOnDelete();
            // Day-of-week index 0–6 (0 = Sunday) — matches Symfony convention.
            $table->unsignedTinyInteger('day');
            $table->string('title');
            $table->longText('content');
            // `passages` is a JSON array of canonical reference strings. See
            // plan.md — these are not parsed at read time by this story.
            $table->json('passages')->nullable();
            // Stable ordering inside a lesson. Day is not unique on its own;
            // multiple segments per day are allowed.
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['sabbath_school_lesson_id', 'position'], 'sabbath_school_segments_lesson_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_segments');
    }
};
