<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\InteractsWithReadingPlans;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowReadingPlanTest extends TestCase
{
    use InteractsWithAuthentication;
    use InteractsWithReadingPlans;
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_the_full_tree_for_a_published_plan(): void
    {
        $plan = $this->givenABilingualPlanWithFragments();

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['plan' => $plan->slug]));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.slug', $plan->slug)
            ->assertJsonCount(2, 'data.days')
            ->assertJsonCount(2, 'data.days.0.fragments');
    }

    public function test_it_resolves_language_per_fragment_with_fallback(): void
    {
        $plan = $this->givenABilingualPlanWithFragments();

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['plan' => $plan->slug, 'language' => 'hu']));

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'EN Name')
            ->assertJsonPath('data.days.0.fragments.0.type', FragmentType::Html->value)
            ->assertJsonPath('data.days.0.fragments.0.content', '<p>EN D1 intro</p>');
    }

    public function test_it_falls_back_to_english_for_an_unsupported_language(): void
    {
        $plan = $this->givenABilingualPlanWithFragments();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['plan' => $plan->slug, 'language' => 'zz']))
            ->assertOk()
            ->assertJsonPath('data.name', 'EN Name')
            ->assertJsonPath('data.days.0.fragments.0.content', '<p>EN D1 intro</p>');
    }

    public function test_it_returns_references_as_raw_strings(): void
    {
        $plan = $this->givenABilingualPlanWithFragments();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['plan' => $plan->slug]))
            ->assertOk()
            ->assertJsonPath('data.days.0.fragments.1.type', FragmentType::References->value)
            ->assertJsonPath('data.days.0.fragments.1.content', ['GEN.1-2', 'MAT.5:27-48']);
    }

    public function test_it_returns_404_for_an_unknown_slug(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['plan' => 'does-not-exist']))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_it_returns_404_for_a_draft_plan(): void
    {
        $plan = ReadingPlan::factory()->draft()->create(['slug' => 'draft-plan']);

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['plan' => $plan->slug]))
            ->assertNotFound();
    }

    public function test_it_rejects_requests_without_an_api_key(): void
    {
        $plan = $this->givenABilingualPlanWithFragments();

        $this->getJson(route('reading-plans.show', ['plan' => $plan->slug]))
            ->assertUnauthorized();

        $this->withHeader('X-Api-Key', 'nope')
            ->getJson(route('reading-plans.show', ['plan' => $plan->slug]))
            ->assertUnauthorized();
    }

    public function test_it_omits_subscriptions_on_api_key_only_requests(): void
    {
        $plan = $this->givenABilingualPlanWithFragments();
        ReadingPlanSubscription::factory()->create(['reading_plan_id' => $plan->id]);

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['plan' => $plan->slug]))
            ->assertOk();

        $this->assertArrayNotHasKey('subscriptions', $response->json('data'));
    }

    public function test_it_surfaces_only_the_authenticated_users_subscriptions(): void
    {
        $plan = $this->givenABilingualPlanWithFragments();
        $day = $plan->days()->orderBy('position')->firstOrFail();

        $bob = User::factory()->create();
        $alice = $this->givenAnAuthenticatedUser();

        $aliceSubscription = $this->givenAnActiveSubscriptionTo($plan, $alice);
        ReadingPlanSubscriptionDay::factory()->completed()->create([
            'reading_plan_subscription_id' => $aliceSubscription->id,
            'reading_plan_day_id' => $day->id,
        ]);

        $bobSubscription = $this->givenAnActiveSubscriptionTo($plan, $bob);
        ReadingPlanSubscriptionDay::factory()->pending()->create([
            'reading_plan_subscription_id' => $bobSubscription->id,
            'reading_plan_day_id' => $day->id,
        ]);

        $response = $this->getJson(route('reading-plans.show', ['plan' => $plan->slug]))
            ->assertOk();

        $response->assertJsonCount(1, 'data.subscriptions');
        $response->assertJsonPath('data.subscriptions.0.id', $aliceSubscription->id);
        $response->assertJsonPath('data.subscriptions.0.progress.completed_days', 1);
        $response->assertJsonPath('data.subscriptions.0.progress.total_days', 1);
    }

    private function givenABilingualPlanWithFragments(): ReadingPlan
    {
        $plan = $this->givenAPublishedReadingPlan([
            'slug' => 'seven-days',
            'name' => ['en' => 'EN Name', 'ro' => 'RO Name'],
            'description' => ['en' => 'EN desc', 'ro' => 'RO desc'],
            'image' => ['en' => 'en.jpg', 'ro' => 'ro.jpg'],
            'thumbnail' => ['en' => 'en-thumb.jpg', 'ro' => 'ro-thumb.jpg'],
        ]);

        foreach ([1, 2] as $position) {
            $day = ReadingPlanDay::factory()->create([
                'reading_plan_id' => $plan->id,
                'position' => $position,
            ]);

            ReadingPlanDayFragment::factory()->create([
                'reading_plan_day_id' => $day->id,
                'position' => 1,
                'type' => FragmentType::Html,
                'content' => [
                    'en' => "<p>EN D{$position} intro</p>",
                    'ro' => "<p>RO D{$position} intro</p>",
                ],
            ]);

            ReadingPlanDayFragment::factory()->create([
                'reading_plan_day_id' => $day->id,
                'position' => 2,
                'type' => FragmentType::References,
                'content' => ['GEN.1-2', 'MAT.5:27-48'],
            ]);
        }

        return $plan;
    }
}
