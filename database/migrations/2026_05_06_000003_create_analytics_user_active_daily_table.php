<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (date, user_id) for any user that emitted at least one
 * event on that date. Drives DAU/MAU computation for authenticated
 * users — the cardinality grows linearly with active users, not with
 * subject ids, so this is intentionally a separate rollup from
 * `analytics_daily_rollups`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_user_active_daily', function (Blueprint $table): void {
            $table->date('date');
            $table->unsignedInteger('user_id');

            $table->primary(['date', 'user_id'], 'analytics_user_active_daily_pk');
            $table->index(['user_id', 'date'], 'analytics_user_active_daily_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_user_active_daily');
    }
};
