<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-row triage queue for user-reported errors on AI-corrected
 * commentary text. The denormalised counter on `commentary_texts.errors_reported`
 * is what the public reader checks; this table is the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commentary_error_reports')) {
            return;
        }

        Schema::create('commentary_error_reports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('commentary_text_id');
            // `users.id` is an unsigned `int` (legacy Symfony shape), not a
            // bigint — match the FK width to keep MySQL happy (error 3780).
            $table->unsignedInteger('user_id')->nullable();
            $table->string('device_id', 64)->nullable();
            $table->string('book', 8);
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse')->nullable();
            $table->text('description');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('commentary_text_id', 'commentary_error_reports_text_id_foreign')
                ->references('id')->on('commentary_texts')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'commentary_error_reports_user_id_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->foreign('reviewed_by_user_id', 'commentary_error_reports_reviewer_id_foreign')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index(['status', 'created_at'], 'commentary_error_reports_status_created_idx');
            $table->index(['commentary_text_id', 'status'], 'commentary_error_reports_text_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commentary_error_reports');
    }
};
