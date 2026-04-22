<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Verses;

use App\Domain\Verses\Models\DailyVerse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class GetDailyVerseTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_it_returns_todays_daily_verse_when_no_date_is_supplied(): void
    {
        $today = DailyVerse::factory()->create([
            'for_date' => now()->toDateString(),
            'reference' => 'GEN.1:1.VDC',
            'image_cdn_url' => 'https://cdn.example/today.jpg',
        ]);

        $response = $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('daily-verse.show'));

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['date', 'reference', 'image_url']])
            ->assertJsonPath('data.date', $today->for_date->format('Y-m-d'))
            ->assertJsonPath('data.reference', 'GEN.1:1.VDC')
            ->assertJsonPath('data.image_url', 'https://cdn.example/today.jpg')
            ->assertHeader('Cache-Control', 'max-age=3600, public');
    }

    public function test_it_returns_the_daily_verse_for_a_past_date(): void
    {
        DailyVerse::factory()->create([
            'for_date' => '2024-12-25',
            'reference' => 'LUK.2:1-7.VDC',
        ]);

        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('daily-verse.show', ['date' => '2024-12-25']))
            ->assertOk()
            ->assertJsonPath('data.date', '2024-12-25')
            ->assertJsonPath('data.reference', 'LUK.2:1-7.VDC');
    }

    public function test_it_returns_404_when_no_daily_verse_is_configured(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('daily-verse.show', ['date' => '2024-01-01']))
            ->assertNotFound()
            ->assertJsonPath('message', 'No daily verse for this date.');
    }

    public function test_it_rejects_future_dates(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('daily-verse.show', ['date' => now()->addDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_it_rejects_malformed_date_format(): void
    {
        $this
            ->withHeaders($this->apiKeyHeaders())
            ->getJson(route('daily-verse.show', ['date' => '12/25/2024']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_it_rejects_missing_auth(): void
    {
        $this
            ->getJson(route('daily-verse.show'))
            ->assertUnauthorized();
    }
}
