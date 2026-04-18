<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Actions;

use App\Domain\ReadingPlans\Actions\AbandonReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Exceptions\SubscriptionAlreadyCompletedException;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AbandonReadingPlanSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_flips_active_to_abandoned(): void
    {
        $subscription = ReadingPlanSubscription::factory()->active()->create();

        $result = $this->app->make(AbandonReadingPlanSubscriptionAction::class)->execute($subscription);

        $this->assertSame(SubscriptionStatus::Abandoned, $result->status);
        $this->assertSame(SubscriptionStatus::Abandoned, $subscription->fresh()?->status);
    }

    public function test_it_is_idempotent_for_already_abandoned(): void
    {
        $subscription = ReadingPlanSubscription::factory()->abandoned()->create();
        $originalUpdatedAt = $subscription->updated_at;

        $result = $this->app->make(AbandonReadingPlanSubscriptionAction::class)->execute($subscription);

        $this->assertSame(SubscriptionStatus::Abandoned, $result->status);
        $this->assertNotNull($originalUpdatedAt);
        $this->assertSame(
            $originalUpdatedAt->toDateTimeString(),
            $subscription->fresh()?->updated_at?->toDateTimeString(),
        );
    }

    public function test_it_throws_when_subscription_is_completed(): void
    {
        $subscription = ReadingPlanSubscription::factory()->completed()->create();

        $this->expectException(SubscriptionAlreadyCompletedException::class);

        $this->app->make(AbandonReadingPlanSubscriptionAction::class)->execute($subscription);
    }

    public function test_it_does_not_soft_delete_the_row(): void
    {
        $subscription = ReadingPlanSubscription::factory()->active()->create();

        $this->app->make(AbandonReadingPlanSubscriptionAction::class)->execute($subscription);

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->deleted_at);
    }
}
