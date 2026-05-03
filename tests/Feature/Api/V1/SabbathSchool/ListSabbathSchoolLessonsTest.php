<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListSabbathSchoolLessonsTest extends TestCase
{
    use InteractsWithAuthentication;
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_paginated_lessons_newest_first(): void
    {
        $older = SabbathSchoolLesson::factory()
            ->publishedAt(CarbonImmutable::now()->subDays(14))
            ->create();
        $newer = SabbathSchoolLesson::factory()
            ->publishedAt(CarbonImmutable::now()->subDay())
            ->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'trimester_id',
                    'language',
                    'age_group',
                    'number',
                    'title',
                    'date_from',
                    'date_to',
                    'week_start',
                    'week_end',
                    'published_at',
                ]],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);
    }

    public function test_it_filters_by_language(): void
    {
        $en = SabbathSchoolLesson::factory()->forLanguage(Language::En)->create();
        SabbathSchoolLesson::factory()->forLanguage(Language::Ro)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.index', ['language' => 'en']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $en->id);
    }

    public function test_it_excludes_unpublished_lessons(): void
    {
        SabbathSchoolLesson::factory()->draft()->create();
        $published = SabbathSchoolLesson::factory()->published()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
    }

    public function test_it_defaults_per_page_to_30(): void
    {
        SabbathSchoolLesson::factory()->count(35)->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.index'))
            ->assertOk()
            ->assertJsonCount(30, 'data')
            ->assertJsonPath('meta.per_page', 30);
    }

    public function test_it_sets_public_cache_headers(): void
    {
        SabbathSchoolLesson::factory()->create();

        $response = $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.index'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
    }

    public function test_it_accepts_sanctum_auth(): void
    {
        SabbathSchoolLesson::factory()->create();
        $this->givenAnAuthenticatedUser();

        $this->getJson(route('sabbath-school.lessons.index'))
            ->assertOk();
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->getJson(route('sabbath-school.lessons.index'))
            ->assertUnauthorized();
    }

    public function test_it_validates_the_language_filter(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->getJson(route('sabbath-school.lessons.index', ['language' => 'fr']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }
}
