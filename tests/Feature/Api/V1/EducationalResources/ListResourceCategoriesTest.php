<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListResourceCategoriesTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_categories_with_resource_count(): void
    {
        $category = ResourceCategory::factory()->forLanguage(Language::En)->create();
        EducationalResource::factory()->forCategory($category)->count(3)->create();

        ResourceCategory::factory()->forLanguage(Language::Ro)->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index'))
            ->assertOk();

        $response
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'description',
                        'language',
                        'resource_count',
                    ],
                ],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);

        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data');
        $withCount = collect($data)->firstWhere('id', $category->id);

        $this->assertIsArray($withCount);
        $this->assertSame(3, $withCount['resource_count']);
    }

    public function test_it_filters_by_language_when_requested(): void
    {
        ResourceCategory::factory()->forLanguage(Language::En)->create();
        ResourceCategory::factory()->forLanguage(Language::Ro)->create();
        ResourceCategory::factory()->forLanguage(Language::Hu)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.language', 'ro');
    }

    public function test_it_resolves_translatable_name_via_request_language(): void
    {
        ResourceCategory::factory()->create([
            'name' => ['en' => 'English Name', 'ro' => 'Nume Român'],
            'description' => ['en' => 'English desc', 'ro' => 'Descriere'],
            'language' => Language::Ro->value,
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Nume Român')
            ->assertJsonPath('data.0.description', 'Descriere');
    }

    public function test_it_falls_back_to_english_for_missing_translations(): void
    {
        ResourceCategory::factory()->forLanguage(Language::Hu)->create([
            'name' => ['en' => 'English Only'],
            'description' => ['en' => 'Only english'],
        ]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index', ['language' => 'hu']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'English Only')
            ->assertJsonPath('data.0.description', 'Only english');
    }

    public function test_it_paginates_with_a_default_of_30_per_page(): void
    {
        ResourceCategory::factory()->count(40)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index'))
            ->assertOk()
            ->assertJsonCount(30, 'data')
            ->assertJsonPath('meta.per_page', 30);
    }

    public function test_it_caps_per_page_validation_at_100(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index', ['per_page' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_it_sets_cache_control_header(): void
    {
        ResourceCategory::factory()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index'))
            ->assertOk();

        $header = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $header);
        $this->assertStringContainsString('max-age=3600', $header);
    }

    public function test_it_rejects_an_unsupported_language(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('resource-categories.index', ['language' => 'fr']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $this->getJson(route('resource-categories.index'))
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_it_rejects_an_unknown_api_key(): void
    {
        $this->withHeader('X-Api-Key', 'not-valid')
            ->getJson(route('resource-categories.index'))
            ->assertUnauthorized();
    }
}
