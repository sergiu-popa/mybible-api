<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (date, device_id) for any device that emitted at least
 * one event on that date. Drives anonymous DAU/MAU.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_device_active_daily', function (Blueprint $table): void {
            $table->date('date');
            $table->string('device_id', 64);

            $table->primary(['date', 'device_id'], 'analytics_device_active_daily_pk');
            $table->index(['device_id', 'date'], 'analytics_device_active_daily_device_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_device_active_daily');
    }
};
