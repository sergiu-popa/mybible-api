<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Enums\SubscriptionStatus;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class AbandonReadingPlanSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_flips_active_subscription_to_abandoned(): void
    {
        $user = User::factory()->create();
        $subscription = ReadingPlanSubscription::factory()->active()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.abandon', ['subscription' => $subscription->id]))
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Abandoned->value);

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(SubscriptionStatus::Abandoned, $fresh->status);
        $this->assertNull($fresh->deleted_at);
    }

    public function test_it_is_idempotent_for_already_abandoned_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = ReadingPlanSubscription::factory()->abandoned()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.abandon', ['subscription' => $subscription->id]))
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Abandoned->value);
    }

    public function test_it_returns_422_when_subscription_is_completed(): void
    {
        $user = User::factory()->create();
        $subscription = ReadingPlanSubscription::factory()->completed()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson(route('reading-plan-subscriptions.abandon', ['subscription' => $subscription->id]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot abandon a completed subscription.');

        $this->assertSame(SubscriptionStatus::Completed, $subscription->fresh()?->status);
    }

    public function test_it_returns_403_for_non_owner(): void
    {
        $subscription = ReadingPlanSubscription::factory()->active()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('reading-plan-subscriptions.abandon', ['subscription' => $subscription->id]))
            ->assertForbidden();
    }

    public function test_it_rejects_missing_sanctum_token(): void
    {
        $subscription = ReadingPlanSubscription::factory()->active()->create();

        $this->postJson(route('reading-plan-subscriptions.abandon', ['subscription' => $subscription->id]))
            ->assertUnauthorized();
    }
}
