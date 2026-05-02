<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('plan')
            && ! Schema::hasTable('plan_day')
            && ! Schema::hasTable('plan_enrollment')
            && ! Schema::hasTable('plan_progress')
        ) {
            return;
        }

        ReconcileTableHelper::rename('plan', 'reading_plans');
        ReconcileTableHelper::rename('plan_day', 'reading_plan_days');
        ReconcileTableHelper::rename('plan_enrollment', 'reading_plan_subscriptions');
        ReconcileTableHelper::renameColumnIfPresent('reading_plan_subscriptions', 'author_id', 'user_id');

        // The legacy plan_progress shape is incompatible with the Laravel
        // `reading_plan_subscription_days`. MBA-031 builds the new shape
        // from this preserved table and then drops it.
        ReconcileTableHelper::rename('plan_progress', 'reading_plan_subscription_days_legacy');
    }

    public function down(): void
    {
        ReconcileTableHelper::rename('reading_plan_subscription_days_legacy', 'plan_progress');
        ReconcileTableHelper::renameColumnIfPresent('reading_plan_subscriptions', 'user_id', 'author_id');
        ReconcileTableHelper::rename('reading_plan_subscriptions', 'plan_enrollment');
        ReconcileTableHelper::rename('reading_plan_days', 'plan_day');
        ReconcileTableHelper::rename('reading_plans', 'plan');
    }
};
