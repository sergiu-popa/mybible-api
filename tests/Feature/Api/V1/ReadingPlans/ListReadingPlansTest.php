<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListReadingPlansTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_published_plans_only(): void
    {
        $published = ReadingPlan::factory()->published()->create();
        ReadingPlan::factory()->draft()->create();
        ReadingPlan::factory()->create([
            'status' => ReadingPlanStatus::Published,
            'published_at' => null,
        ]);

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.slug', $published->slug);
    }

    public function test_it_resolves_language_and_falls_back_to_english(): void
    {
        ReadingPlan::factory()->published()->create([
            'slug' => 'only-english',
            'name' => ['en' => 'English Title'],
            'description' => ['en' => 'English description.'],
            'image' => ['en' => 'https://cdn.example.com/en.jpg'],
            'thumbnail' => ['en' => 'https://cdn.example.com/thumb-en.jpg'],
        ]);

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index', ['language' => 'hu']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'English Title')
            ->assertJsonPath('data.0.description', 'English description.');
    }

    public function test_it_honours_the_requested_language_when_present(): void
    {
        ReadingPlan::factory()->published()->create([
            'slug' => 'bilingual',
            'name' => ['en' => 'English', 'ro' => 'Română'],
            'description' => ['en' => 'EN desc', 'ro' => 'RO desc'],
            'image' => ['en' => 'en.jpg', 'ro' => 'ro.jpg'],
            'thumbnail' => ['en' => 'en-thumb.jpg', 'ro' => 'ro-thumb.jpg'],
        ]);

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Română')
            ->assertJsonPath('data.0.description', 'RO desc');
    }

    public function test_it_falls_back_to_english_for_an_unsupported_language(): void
    {
        ReadingPlan::factory()->published()->create([
            'slug' => 'only-english',
            'name' => ['en' => 'English Title'],
            'description' => ['en' => 'English description.'],
            'image' => ['en' => 'https://cdn.example.com/en.jpg'],
            'thumbnail' => ['en' => 'https://cdn.example.com/thumb-en.jpg'],
        ]);

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index', ['language' => 'fr']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'English Title')
            ->assertJsonPath('data.0.description', 'English description.');
    }

    public function test_it_paginates_with_a_default_of_15_per_page(): void
    {
        ReadingPlan::factory()->count(20)->published()->create();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index'))
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_it_caps_per_page_validation_at_100(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index', ['per_page' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_it_honours_a_valid_per_page(): void
    {
        ReadingPlan::factory()->count(5)->published()->create();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index', ['per_page' => 3]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3);
    }

    public function test_it_does_not_include_days_on_the_list_endpoint(): void
    {
        ReadingPlan::factory()->published()->create();

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index'));

        $response->assertOk();
        $this->assertArrayNotHasKey('days', $response->json('data.0'));
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $this->getJson(route('reading-plans.index'))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_it_rejects_an_unknown_api_key(): void
    {
        $this->withHeader('X-Api-Key', 'not-valid')
            ->getJson(route('reading-plans.index'))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_it_returns_the_expected_shape(): void
    {
        ReadingPlan::factory()->published()->create();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'slug',
                        'name',
                        'description',
                        'image',
                        'thumbnail',
                        'published_at',
                    ],
                ],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);
    }

    public function test_it_omits_subscriptions_on_api_key_only_requests(): void
    {
        $plan = ReadingPlan::factory()->published()->create();
        ReadingPlanSubscription::factory()->create(['reading_plan_id' => $plan->id]);

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.index'));

        $response->assertOk();
        $this->assertArrayNotHasKey('subscriptions', $response->json('data.0'));
    }

    public function test_it_returns_only_the_authenticated_users_subscriptions_with_progress(): void
    {
        $plan = ReadingPlan::factory()->published()->create();
        $day = ReadingPlanDay::factory()->create(['reading_plan_id' => $plan->id, 'position' => 1]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceSubscription = ReadingPlanSubscription::factory()->create([
            'user_id' => $alice->id,
            'reading_plan_id' => $plan->id,
        ]);
        ReadingPlanSubscriptionDay::factory()->completed()->create([
            'reading_plan_subscription_id' => $aliceSubscription->id,
            'reading_plan_day_id' => $day->id,
        ]);

        $bobSubscription = ReadingPlanSubscription::factory()->create([
            'user_id' => $bob->id,
            'reading_plan_id' => $plan->id,
        ]);
        ReadingPlanSubscriptionDay::factory()->pending()->create([
            'reading_plan_subscription_id' => $bobSubscription->id,
            'reading_plan_day_id' => $day->id,
        ]);

        $token = $alice->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('reading-plans.index'))
            ->assertOk();

        $response->assertJsonCount(1, 'data.0.subscriptions');
        $response->assertJsonPath('data.0.subscriptions.0.id', $aliceSubscription->id);
        $response->assertJsonPath('data.0.subscriptions.0.progress.completed_days', 1);
        $response->assertJsonPath('data.0.subscriptions.0.progress.total_days', 1);
    }
}
