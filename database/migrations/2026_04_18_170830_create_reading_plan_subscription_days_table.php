<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_plan_subscription_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reading_plan_subscription_id');
            $table->foreignId('reading_plan_day_id');
            $table->foreign('reading_plan_subscription_id', 'rp_sub_days_subscription_fk')
                ->references('id')->on('reading_plan_subscriptions')
                ->cascadeOnDelete();
            $table->foreign('reading_plan_day_id', 'rp_sub_days_day_fk')
                ->references('id')->on('reading_plan_days')
                ->restrictOnDelete();
            $table->date('scheduled_date');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['reading_plan_subscription_id', 'reading_plan_day_id'],
                'rp_sub_days_subscription_day_unique',
            );
            $table->index(
                ['reading_plan_subscription_id', 'scheduled_date'],
                'rp_sub_days_subscription_scheduled_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_plan_subscription_days');
    }
};
