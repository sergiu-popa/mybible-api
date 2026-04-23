<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListResourcesByCategoryTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_resources_for_the_given_category(): void
    {
        $category = ResourceCategory::factory()->create();
        $other = ResourceCategory::factory()->create();

        EducationalResource::factory()->forCategory($category)->count(3)->create();
        EducationalResource::factory()->forCategory($other)->count(2)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.resources.index', ['category' => $category->id]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'uuid',
                        'type',
                        'title',
                        'summary',
                        'thumbnail_url',
                        'published_at',
                    ],
                ],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);
    }

    public function test_it_orders_resources_newest_first(): void
    {
        $category = ResourceCategory::factory()->create();

        $older = EducationalResource::factory()->forCategory($category)->create([
            'published_at' => now()->subDays(5),
        ]);
        $newer = EducationalResource::factory()->forCategory($category)->create([
            'published_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.resources.index', ['category' => $category->id]))
            ->assertOk();

        $this->assertSame($newer->uuid, $response->json('data.0.uuid'));
        $this->assertSame($older->uuid, $response->json('data.1.uuid'));
    }

    public function test_it_filters_by_type(): void
    {
        $category = ResourceCategory::factory()->create();
        $article = EducationalResource::factory()
            ->forCategory($category)
            ->ofType(ResourceType::Article)
            ->create();
        EducationalResource::factory()
            ->forCategory($category)
            ->ofType(ResourceType::Video)
            ->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.resources.index', [
                'category' => $category->id,
                'type' => 'article',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $article->uuid)
            ->assertJsonPath('data.0.type', 'article');
    }

    public function test_it_rejects_invalid_type_filter(): void
    {
        $category = ResourceCategory::factory()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.resources.index', [
                'category' => $category->id,
                'type' => 'bogus',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_it_paginates_with_a_default_of_25_per_page(): void
    {
        $category = ResourceCategory::factory()->create();
        EducationalResource::factory()->forCategory($category)->count(30)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.resources.index', ['category' => $category->id]))
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_it_returns_404_for_unknown_category(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.resources.index', ['category' => 999_999]))
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $category = ResourceCategory::factory()->create();

        $this->getJson(route('resource-categories.resources.index', ['category' => $category->id]))
            ->assertUnauthorized();
    }
}
