<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Olympiad;

use App\Domain\Olympiad\Models\OlympiadQuestion;
use App\Domain\Shared\Enums\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListOlympiadThemesControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_rejects_missing_api_key(): void
    {
        $this->getJson(route('olympiad.themes.index'))
            ->assertUnauthorized();
    }

    public function test_it_returns_themes_for_the_resolved_language(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->count(2)->create();
        OlympiadQuestion::factory()->forTheme('EXO', 1, 2, Language::Ro)->count(3)->create();

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.index'))
            ->assertOk();

        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.book', 'GEN')
            ->assertJsonPath('data.0.chapters_from', 1)
            ->assertJsonPath('data.0.chapters_to', 3)
            ->assertJsonPath('data.0.language', 'en')
            ->assertJsonPath('data.0.question_count', 2)
            ->assertJsonPath('data.0.id', 'GEN.1-3.en');
    }

    public function test_it_filters_by_requested_language(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->count(2)->create();
        OlympiadQuestion::factory()->forTheme('EXO', 1, 2, Language::Ro)->count(3)->create();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.index', ['language' => 'ro']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.book', 'EXO')
            ->assertJsonPath('data.0.language', 'ro')
            ->assertJsonPath('data.0.question_count', 3);
    }

    public function test_it_paginates_with_a_default_of_50_per_page(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            OlympiadQuestion::factory()->forTheme('GEN', $i, $i)->create();
        }

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.index'))
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_it_sets_cache_control_header(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->create();

        $response = $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.index'))
            ->assertOk();

        $this->assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
    }

    public function test_it_returns_the_expected_shape(): void
    {
        OlympiadQuestion::factory()->forTheme('GEN', 1, 3)->create();

        $this->withHeader('X-Api-Key', 'mobile-valid-key')
            ->getJson(route('olympiad.themes.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'book',
                        'chapters_from',
                        'chapters_to',
                        'language',
                        'question_count',
                    ],
                ],
                'meta' => ['per_page', 'current_page', 'total'],
                'links',
            ]);
    }
}
