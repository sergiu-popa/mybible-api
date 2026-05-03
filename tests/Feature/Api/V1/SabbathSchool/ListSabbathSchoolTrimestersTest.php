<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListSabbathSchoolTrimestersTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_trimesters_ordered_by_year_desc_number_desc(): void
    {
        $older = SabbathSchoolTrimester::factory()
            ->forLanguage(Language::En)
            ->create(['year' => '2024', 'number' => 4]);
        $newer = SabbathSchoolTrimester::factory()
            ->forLanguage(Language::En)
            ->create(['year' => '2026', 'number' => 1]);

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.trimesters.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_it_filters_by_resolved_language(): void
    {
        $en = SabbathSchoolTrimester::factory()->forLanguage(Language::En)->create();
        SabbathSchoolTrimester::factory()->forLanguage(Language::Ro)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.trimesters.index', ['language' => 'en']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $en->id);
    }

    public function test_it_sets_public_cache_headers(): void
    {
        SabbathSchoolTrimester::factory()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.trimesters.index'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('sabbath-school.trimesters.index'))
            ->assertUnauthorized();
    }
}
