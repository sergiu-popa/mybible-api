<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ListDevotionalArchiveTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_paginated_archive_newest_first(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        foreach (['2026-03-01', '2026-04-10', '2026-04-22', '2026-04-05'] as $date) {
            Devotional::factory()
                ->adults()
                ->forLanguage(Language::Ro)
                ->onDate(CarbonImmutable::parse($date))
                ->create();
        }

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.archive', ['type' => 'adults', 'language' => 'ro']));

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'date']], 'meta', 'links']);

        $dates = array_column($response->json('data'), 'date');

        $this->assertSame(['2026-04-22', '2026-04-10', '2026-04-05', '2026-03-01'], $dates);

        Carbon::setTestNow();
    }

    public function test_it_filters_by_from_and_to(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        foreach (['2026-03-01', '2026-04-10', '2026-04-22'] as $date) {
            Devotional::factory()
                ->adults()
                ->forLanguage(Language::Ro)
                ->onDate(CarbonImmutable::parse($date))
                ->create();
        }

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.archive', [
                'type' => 'adults',
                'language' => 'ro',
                'from' => '2026-04-01',
                'to' => '2026-04-15',
            ]))
            ->assertOk();

        $dates = array_column($response->json('data'), 'date');

        $this->assertSame(['2026-04-10'], $dates);

        Carbon::setTestNow();
    }

    public function test_it_excludes_future_dated_entries(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        Devotional::factory()->adults()->forLanguage(Language::Ro)->onDate(CarbonImmutable::parse('2026-04-22'))->create();
        Devotional::factory()->adults()->forLanguage(Language::Ro)->onDate(CarbonImmutable::parse('2026-04-23'))->create();

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.archive', ['type' => 'adults', 'language' => 'ro']))
            ->assertOk();

        $dates = array_column($response->json('data'), 'date');

        $this->assertSame(['2026-04-22'], $dates);

        Carbon::setTestNow();
    }

    public function test_it_does_not_mix_types(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        Devotional::factory()->adults()->forLanguage(Language::Ro)->onDate(CarbonImmutable::parse('2026-04-22'))->create();
        Devotional::factory()->kids()->forLanguage(Language::Ro)->onDate(CarbonImmutable::parse('2026-04-22'))->create();

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.archive', ['type' => 'kids', 'language' => 'ro']))
            ->assertOk();

        $types = array_column($response->json('data'), 'type');

        $this->assertSame(['kids'], $types);

        Carbon::setTestNow();
    }

    public function test_it_caps_per_page(): void
    {
        Carbon::setTestNow('2026-04-22 09:00:00');

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.archive', [
                'type' => 'adults',
                'language' => 'ro',
                'per_page' => 999,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        Carbon::setTestNow();
    }

    public function test_it_rejects_to_before_from(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('devotionals.archive', [
                'type' => 'adults',
                'from' => '2026-05-01',
                'to' => '2026-04-22',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this
            ->getJson(route('devotionals.archive', ['type' => 'adults']))
            ->assertUnauthorized();
    }
}
