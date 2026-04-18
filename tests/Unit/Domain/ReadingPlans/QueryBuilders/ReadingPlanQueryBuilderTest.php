<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ReadingPlans\QueryBuilders;

use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReadingPlanQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_excludes_drafts_and_unpublished(): void
    {
        $published = ReadingPlan::factory()->published()->create();
        $draft = ReadingPlan::factory()->draft()->create();
        ReadingPlan::factory()->create([
            'status' => ReadingPlanStatus::Published,
            'published_at' => null,
        ]);

        $ids = ReadingPlan::query()->published()->pluck('id')->all();

        $this->assertSame([$published->id], $ids);
        $this->assertNotContains($draft->id, $ids);
    }
}
