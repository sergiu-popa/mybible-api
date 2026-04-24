<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sabbath_school_favorites')) {
            return;
        }

        Schema::create('sabbath_school_favorites', function (Blueprint $table): void {
            $table->id();
            // users.id is unsigned INT (Symfony schema preserved via increments('id')),
            // so user_id has to match width — foreignId would create bigint and fail FK.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('sabbath_school_lesson_id')
                ->constrained('sabbath_school_lessons')
                ->cascadeOnDelete();
            // Sentinel column: 0 = whole-lesson favorite, non-zero = segment-scoped
            // favorite. No FK — the sentinel value would never match a real
            // sabbath_school_segments.id. Referential integrity for non-sentinel
            // values is enforced at the Form Request layer (segment must belong
            // to the lesson).
            $table->unsignedBigInteger('sabbath_school_segment_id')->default(0);
            $table->timestamps();

            $table->unique(
                ['user_id', 'sabbath_school_lesson_id', 'sabbath_school_segment_id'],
                'sabbath_school_favorites_user_lesson_segment_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_favorites');
    }
};
