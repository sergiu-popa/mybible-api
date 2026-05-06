<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated daily counts keyed by
 * `(date, event_type, subject_type, subject_id, language)`. Populated
 * by `RollupAnalyticsForDateAction` under a delete-then-insert
 * transaction so reruns are idempotent. A composite primary key over
 * the five dimensions enforces uniqueness and acts as the lookup
 * index for the admin read endpoints.
 *
 * `subject_type`, `subject_id`, and `language` are technically
 * NULLABLE (not every event has a subject or a language), but MySQL
 * cannot include NULLABLE columns in a PRIMARY KEY. We therefore
 * declare them NOT NULL with a sentinel value of `''` / `0` written
 * by the rollup job. Read endpoints translate the sentinels back to
 * `null` when shaping the JSON response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_rollups', function (Blueprint $table): void {
            $table->date('date');
            $table->string('event_type', 64);
            $table->string('subject_type', 64)->default('');
            $table->unsignedBigInteger('subject_id')->default(0);
            $table->char('language', 2)->default('');
            $table->unsignedInteger('event_count');
            $table->unsignedInteger('unique_users');
            $table->unsignedInteger('unique_devices');

            $table->primary(
                ['date', 'event_type', 'subject_type', 'subject_id', 'language'],
                'analytics_daily_rollups_pk',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_rollups');
    }
};
