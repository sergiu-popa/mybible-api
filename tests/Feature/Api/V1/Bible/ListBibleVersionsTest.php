<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Bible;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListBibleVersionsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_lists_all_versions_for_authenticated_api_key(): void
    {
        BibleVersion::factory()->count(3)->create();

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('bible-versions.index'));

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'abbreviation', 'language']],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ])
            ->assertHeader('Cache-Control', 'max-age=3600, public');

        $this->assertNotEmpty($response->headers->get('ETag'));
    }

    public function test_it_filters_by_language(): void
    {
        $romanian = BibleVersion::factory()->romanian()->create();
        BibleVersion::factory()->create(['language' => Language::En->value]);

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('bible-versions.index', ['language' => 'ro']));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $romanian->id)
            ->assertJsonPath('data.0.language', 'ro');
    }

    public function test_it_defaults_per_page_to_fifty(): void
    {
        BibleVersion::factory()->count(3)->create();

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('bible-versions.index'))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_it_rejects_per_page_over_one_hundred(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('bible-versions.index', ['per_page' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $this->getJson(route('bible-versions.index'))
            ->assertUnauthorized();
    }

    public function test_it_returns_304_when_if_none_match_matches_etag(): void
    {
        BibleVersion::factory()->count(2)->create();

        $first = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('bible-versions.index'));

        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this
            ->withHeaders($this->apiKeyHeaders() + ['If-None-Match' => $etag])
            ->getJson(route('bible-versions.index'))
            ->assertStatus(304);
    }
}
