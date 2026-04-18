<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\Models;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_route_binding_returns_published_plan_by_slug(): void
    {
        $plan = ReadingPlan::factory()->published()->create(['slug' => 'my-plan']);

        $resolved = (new ReadingPlan)->resolveRouteBinding('my-plan', 'slug');

        $this->assertInstanceOf(ReadingPlan::class, $resolved);
        $this->assertSame($plan->id, $resolved->id);
    }

    public function test_resolve_route_binding_returns_null_for_draft_plan(): void
    {
        ReadingPlan::factory()->draft()->create(['slug' => 'draft-plan']);

        $resolved = (new ReadingPlan)->resolveRouteBinding('draft-plan', 'slug');

        $this->assertNull($resolved);
    }

    public function test_resolve_route_binding_returns_null_for_published_status_without_published_at(): void
    {
        ReadingPlan::factory()->published()->create([
            'slug' => 'pending-publish',
            'published_at' => null,
        ]);

        $resolved = (new ReadingPlan)->resolveRouteBinding('pending-publish', 'slug');

        $this->assertNull($resolved);
    }

    public function test_resolve_route_binding_returns_null_for_unknown_slug(): void
    {
        $resolved = (new ReadingPlan)->resolveRouteBinding('does-not-exist', 'slug');

        $this->assertNull($resolved);
    }
}
