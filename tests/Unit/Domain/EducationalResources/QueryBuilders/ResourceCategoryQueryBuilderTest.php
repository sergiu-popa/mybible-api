<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\EducationalResources\QueryBuilders;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResourceCategoryQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_resource_count_populates_the_aggregate(): void
    {
        $category = ResourceCategory::factory()->create();
        EducationalResource::factory()->forCategory($category)->count(4)->create();

        $row = ResourceCategory::query()
            ->withResourceCount()
            ->where('id', $category->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(4, (int) $row->resource_count);
    }

    public function test_for_language_filters_matching_rows(): void
    {
        $en = ResourceCategory::factory()->forLanguage(Language::En)->create();
        ResourceCategory::factory()->forLanguage(Language::Ro)->create();

        $ids = ResourceCategory::query()
            ->forLanguage(Language::En)
            ->pluck('id')
            ->all();

        $this->assertSame([$en->id], $ids);
    }
}
