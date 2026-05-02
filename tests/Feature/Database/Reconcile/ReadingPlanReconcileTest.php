<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Reconcile;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Assert;

final class ReadingPlanReconcileTest extends ReconcileTestCase
{
    use RefreshDatabase;

    public function test_it_renames_plan_tables_and_renames_author_id_to_user_id(): void
    {
        Schema::dropIfExists('reading_plan_subscription_days');
        Schema::dropIfExists('reading_plan_subscriptions');
        Schema::dropIfExists('reading_plan_day_fragments');
        Schema::dropIfExists('reading_plan_days');
        Schema::dropIfExists('reading_plans');

        $this->recreateLegacyTable('plan', function (Blueprint $t): void {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });

        $this->recreateLegacyTable('plan_day', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('plan_id');
            $t->unsignedSmallInteger('position');
        });

        $this->recreateLegacyTable('plan_enrollment', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('author_id');
            $t->unsignedBigInteger('plan_id');
            $t->timestamps();
        });

        $this->recreateLegacyTable('plan_progress', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('enrollment_id');
            $t->unsignedSmallInteger('day_position');
        });

        $migration = $this->loadMigration('2026_05_03_000206_reconcile_symfony_reading_plan_tables.php');
        Assert::assertTrue(method_exists($migration, 'up'));
        $migration->up();

        $this->assertTableExists('reading_plans');
        $this->assertTableExists('reading_plan_days');
        $this->assertTableExists('reading_plan_subscriptions');
        $this->assertTableExists('reading_plan_subscription_days_legacy');

        $this->assertTableMissing('plan');
        $this->assertTableMissing('plan_day');
        $this->assertTableMissing('plan_enrollment');
        $this->assertTableMissing('plan_progress');

        $this->assertColumnExists('reading_plan_subscriptions', 'user_id');
        $this->assertFalse(Schema::hasColumn('reading_plan_subscriptions', 'author_id'));
    }
}
