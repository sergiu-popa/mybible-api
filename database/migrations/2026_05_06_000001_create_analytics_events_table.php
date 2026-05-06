<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `analytics_events` is the polymorphic raw event store. The table is
 * expected to grow indefinitely (per stakeholder decision D); a future
 * story will partition or archive it. Dashboard queries hit the
 * pre-aggregated rollups, never this table directly except for the
 * reading-plan funnel and any metadata cut not promoted to a rollup
 * column yet (e.g. `metadata.version_abbreviation`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('event_type', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('device_id', 64)->nullable();
            $table->char('language', 2)->nullable();
            $table->string('source', 16)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'occurred_at'], 'analytics_events_type_occurred_idx');
            $table->index(
                ['subject_type', 'subject_id', 'occurred_at'],
                'analytics_events_subject_occurred_idx',
            );
            $table->index(['user_id', 'occurred_at'], 'analytics_events_user_occurred_idx');
            $table->index(['device_id', 'occurred_at'], 'analytics_events_device_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
