<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\EducationalResources\QueryBuilders;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EducationalResourceQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_of_type_filters_by_the_enum_value(): void
    {
        $category = ResourceCategory::factory()->create();

        $video = EducationalResource::factory()
            ->forCategory($category)
            ->ofType(ResourceType::Video)
            ->create();
        EducationalResource::factory()
            ->forCategory($category)
            ->ofType(ResourceType::Article)
            ->create();

        $ids = EducationalResource::query()
            ->ofType(ResourceType::Video)
            ->pluck('id')
            ->all();

        $this->assertSame([$video->id], $ids);
    }

    public function test_latest_published_orders_newest_first(): void
    {
        $category = ResourceCategory::factory()->create();

        $older = EducationalResource::factory()->forCategory($category)->create([
            'published_at' => now()->subDays(5),
        ]);
        $newest = EducationalResource::factory()->forCategory($category)->create([
            'published_at' => now()->subHour(),
        ]);
        $middle = EducationalResource::factory()->forCategory($category)->create([
            'published_at' => now()->subDay(),
        ]);

        $ids = EducationalResource::query()
            ->latestPublished()
            ->pluck('id')
            ->all();

        $this->assertSame([$newest->id, $middle->id, $older->id], $ids);
    }
}
