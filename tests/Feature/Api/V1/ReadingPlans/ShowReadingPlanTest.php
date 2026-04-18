<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('api_keys.header', 'X-Api-Key');
        config()->set('api_keys.clients', [
            'mobile' => 'mobile-valid-key',
        ]);
    }

    public function test_it_returns_the_full_tree_for_a_published_plan(): void
    {
        $plan = $this->seedPublishedPlan();

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['slug' => $plan->slug]));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.slug', $plan->slug)
            ->assertJsonCount(2, 'data.days')
            ->assertJsonCount(2, 'data.days.0.fragments');
    }

    public function test_it_resolves_language_per_fragment_with_fallback(): void
    {
        $plan = $this->seedPublishedPlan();

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['slug' => $plan->slug, 'language' => 'hu']));

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'EN Name')
            ->assertJsonPath('data.days.0.fragments.0.type', FragmentType::Html->value)
            ->assertJsonPath('data.days.0.fragments.0.content', '<p>EN D1 intro</p>');
    }

    public function test_it_returns_references_as_raw_strings(): void
    {
        $plan = $this->seedPublishedPlan();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['slug' => $plan->slug]))
            ->assertOk()
            ->assertJsonPath('data.days.0.fragments.1.type', FragmentType::References->value)
            ->assertJsonPath('data.days.0.fragments.1.content', ['GEN.1-2', 'MAT.5:27-48']);
    }

    public function test_it_returns_404_for_an_unknown_slug(): void
    {
        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['slug' => 'does-not-exist']))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_it_returns_404_for_a_draft_plan(): void
    {
        $plan = ReadingPlan::factory()->draft()->create(['slug' => 'draft-plan']);

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('reading-plans.show', ['slug' => $plan->slug]))
            ->assertNotFound();
    }

    public function test_it_rejects_requests_without_an_api_key(): void
    {
        $plan = $this->seedPublishedPlan();

        $this->getJson(route('reading-plans.show', ['slug' => $plan->slug]))
            ->assertUnauthorized();

        $this->withHeader('X-Api-Key', 'nope')
            ->getJson(route('reading-plans.show', ['slug' => $plan->slug]))
            ->assertUnauthorized();
    }

    private function seedPublishedPlan(): ReadingPlan
    {
        $plan = ReadingPlan::factory()->published()->create([
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
