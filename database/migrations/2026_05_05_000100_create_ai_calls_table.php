<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit log of every Anthropic Claude API call. Polymorphic
 * `subject_*` columns let any feature attach a call to its row without
 * dedicated columns. No `updated_at` — rows are written once on the call
 * outcome and never mutated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_calls')) {
            return;
        }

        Schema::create('ai_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('prompt_version', 20);
            $table->string('prompt_name', 64);
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_creation_input_tokens')->default(0);
            $table->unsignedInteger('cache_read_input_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 16);
            $table->text('error_message')->nullable();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // `users.id` is an unsigned int (legacy Symfony shape), not bigint
            // — match width to keep the FK constraint engine-compatible.
            $table->unsignedInteger('triggered_by_user_id')->nullable();
            $table->foreign('triggered_by_user_id', 'ai_calls_user_id_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['prompt_name', 'created_at'], 'ai_calls_prompt_created_idx');
            $table->index(['subject_type', 'subject_id'], 'ai_calls_subject_idx');
            $table->index(['triggered_by_user_id', 'created_at'], 'ai_calls_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
