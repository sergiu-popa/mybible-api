<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olympiad_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('book', 8);
            $table->unsignedSmallInteger('chapters_from');
            $table->unsignedSmallInteger('chapters_to');
            $table->string('language', 8);
            $table->text('question');
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(
                ['language', 'book', 'chapters_from', 'chapters_to'],
                'olympiad_questions_theme_idx',
            );
        });

        Schema::create('olympiad_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olympiad_question_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(
                ['olympiad_question_id', 'position'],
                'olympiad_answers_question_pos_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olympiad_answers');
        Schema::dropIfExists('olympiad_questions');
    }
};
