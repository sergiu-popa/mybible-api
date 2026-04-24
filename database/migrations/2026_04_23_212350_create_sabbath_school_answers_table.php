<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sabbath_school_answers')) {
            return;
        }

        Schema::create('sabbath_school_answers', function (Blueprint $table): void {
            $table->id();
            // users.id is unsigned INT (Symfony schema preserved via increments('id')),
            // so user_id has to match width — foreignId would create bigint and fail FK.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('sabbath_school_question_id')
                ->constrained('sabbath_school_questions')
                ->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();

            // One answer per user per question — enforces upsert semantics at DB level.
            $table->unique(
                ['user_id', 'sabbath_school_question_id'],
                'sabbath_school_answers_user_question_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sabbath_school_answers');
    }
};
