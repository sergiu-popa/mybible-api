<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('olympiad_attempts')) {
            Schema::create('olympiad_attempts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
                $table->string('book', 8);
                $table->string('chapters_label', 32);
                $table->char('language', 2);
                $table->unsignedSmallInteger('score');
                $table->unsignedSmallInteger('total');
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'completed_at'], 'olympiad_attempts_user_completed_idx');
                $table->index(['language', 'book', 'chapters_label'], 'olympiad_attempts_theme_idx');
            });
        }

        if (! Schema::hasTable('olympiad_attempt_answers')) {
            Schema::create('olympiad_attempt_answers', function (Blueprint $table): void {
                $table->foreignId('attempt_id')
                    ->constrained('olympiad_attempts')
                    ->cascadeOnDelete();
                $table->foreignId('olympiad_question_id')
                    ->constrained('olympiad_questions')
                    ->cascadeOnDelete();
                $table->foreignId('selected_answer_id')
                    ->nullable()
                    ->constrained('olympiad_answers')
                    ->nullOnDelete();
                $table->boolean('is_correct');
                $table->timestamps();

                $table->primary(['attempt_id', 'olympiad_question_id'], 'olympiad_attempt_answers_pk');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('olympiad_attempt_answers');
        Schema::dropIfExists('olympiad_attempts');
    }
};
