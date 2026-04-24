<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sabbath_school_questions')) {
            return;
        }

        Schema::create('sabbath_school_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sabbath_school_segment_id')
                ->constrained('sabbath_school_segments')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('prompt');
            $table->timestamps();

            $table->index(['sabbath_school_segment_id', 'position'], 'sabbath_school_questions_segment_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_questions');
    }
};
