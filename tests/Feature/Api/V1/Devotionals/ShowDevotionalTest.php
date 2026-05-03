<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalType;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ShowDevotionalTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_todays_devotional_when_date_is_omitted(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        $today = Devotional::factory()
            ->adults()
            ->forLanguage(Language::Ro)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->create(['title' => 'Astazi']);

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', ['type' => 'adults', 'language' => 'ro']));

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'date', 'type', 'language', 'title', 'content']])
            ->assertJsonPath('data.id', $today->id)
            ->assertJsonPath('data.date', '2026-04-22')
            ->assertJsonPath('data.type', 'adults')
            ->assertJsonPath('data.language', 'ro')
            ->assertJsonPath('data.title', 'Astazi');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);

        Carbon::setTestNow();
    }

    public function test_it_returns_the_devotional_for_a_specific_date(): void
    {
        Devotional::factory()->kids()->forLanguage(Language::Ro)->onDate(CarbonImmutable::parse('2026-01-15'))->create([
            'title' => 'Past day',
        ]);

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', [
                'type' => 'kids',
                'language' => 'ro',
                'date' => '2026-01-15',
            ]))
            ->assertOk()
            ->assertJsonPath('data.title', 'Past day')
            ->assertJsonPath('data.type', 'kids');
    }

    public function test_it_returns_404_when_no_devotional_matches_the_tuple(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', [
                'type' => 'adults',
                'language' => 'ro',
                'date' => '2026-04-22',
            ]))
            ->assertNotFound();
    }

    public function test_it_does_not_fall_back_across_languages(): void
    {
        Devotional::factory()
            ->adults()
            ->forLanguage(Language::Hu)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->create();

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', [
                'type' => 'adults',
                'language' => 'ro',
                'date' => '2026-04-22',
            ]))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_unknown_type_slug(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', ['type' => 'toddlers']))
            ->assertNotFound();
    }

    public function test_it_resolves_admin_defined_type_slug(): void
    {
        $youth = DevotionalType::factory()->create([
            'slug' => 'youth',
            'title' => 'Youth',
            'language' => null,
        ]);

        $devo = Devotional::factory()
            ->ofType($youth)
            ->forLanguage(Language::Ro)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->create(['title' => 'Youth devotional']);

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', [
                'type' => 'youth',
                'language' => 'ro',
                'date' => '2026-04-22',
            ]))
            ->assertOk()
            ->assertJsonPath('data.id', $devo->id)
            ->assertJsonPath('data.type', 'youth');
    }

    public function test_it_prefers_language_specific_type_over_global(): void
    {
        $globalYouth = DevotionalType::factory()->create([
            'slug' => 'youth',
            'title' => 'Youth (global)',
            'language' => null,
        ]);
        $roYouth = DevotionalType::factory()->create([
            'slug' => 'youth',
            'title' => 'Youth (RO)',
            'language' => 'ro',
        ]);

        $devo = Devotional::factory()
            ->ofType($roYouth)
            ->forLanguage(Language::Ro)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->create();

        Devotional::factory()
            ->ofType($globalYouth)
            ->forLanguage(Language::Ro)
            ->onDate(CarbonImmutable::parse('2026-04-22'))
            ->create();

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', [
                'type' => 'youth',
                'language' => 'ro',
                'date' => '2026-04-22',
            ]))
            ->assertOk()
            ->assertJsonPath('data.id', $devo->id);
    }

    public function test_it_rejects_malformed_date(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.show', ['type' => 'adults', 'date' => '22/04/2026']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this
            ->getJson(route('devotionals.show', ['type' => 'adults']))
            ->assertUnauthorized();
    }
}
