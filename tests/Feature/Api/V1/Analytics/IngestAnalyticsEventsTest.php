<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Analytics;

use App\Application\Jobs\RecordAnalyticsEventJob;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class IngestAnalyticsEventsTest extends TestCase
{
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
        RateLimiter::clear('analytics-ingest');
    }

    public function test_anonymous_request_accepts_a_batch(): void
    {
        Queue::fake();

        $payload = [
            'events' => [[
                'event_type' => 'devotional.viewed',
                'subject_type' => 'devotional',
                'subject_id' => 42,
                'language' => 'ro',
                'occurred_at' => '2026-05-06T12:00:00Z',
            ]],
            'device_id' => 'device-123',
        ];

        $this->withHeaders($this->apiKeyHeaders() + ['User-Agent' => 'Mozilla/5.0 Chrome/100'])
            ->postJson(route('analytics.events.store'), $payload)
            ->assertNoContent();

        Queue::assertPushed(RecordAnalyticsEventJob::class, 1);
    }

    public function test_authenticated_request_captures_user_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $payload = [
            'events' => [[
                'event_type' => 'auth.login',
                'occurred_at' => '2026-05-06T12:00:00Z',
            ]],
        ];

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'Mozilla/5.0 Chrome/100',
        ])
            ->postJson(route('analytics.events.store'), $payload)
            ->assertNoContent();

        $this->assertDatabaseHas('analytics_events', [
            'event_type' => 'auth.login',
            'user_id' => $user->id,
            'source' => 'web',
        ]);
    }

    public function test_infers_ios_source_from_user_agent(): void
    {
        $payload = [
            'events' => [[
                'event_type' => 'devotional.viewed',
                'subject_type' => 'devotional',
                'subject_id' => 1,
                'occurred_at' => '2026-05-06T12:00:00Z',
            ]],
            'device_id' => 'd1',
        ];

        $this->withHeaders($this->apiKeyHeaders() + ['User-Agent' => 'MyBibleMobile/1.0 ios'])
            ->postJson(route('analytics.events.store'), $payload)
            ->assertNoContent();

        $this->assertDatabaseHas('analytics_events', ['source' => 'ios']);
    }

    public function test_infers_android_source_from_user_agent(): void
    {
        $payload = [
            'events' => [[
                'event_type' => 'devotional.viewed',
                'subject_type' => 'devotional',
                'subject_id' => 1,
                'occurred_at' => '2026-05-06T12:00:00Z',
            ]],
            'device_id' => 'd1',
        ];

        $this->withHeaders($this->apiKeyHeaders() + ['User-Agent' => 'MyBibleMobile/1.0 android'])
            ->postJson(route('analytics.events.store'), $payload)
            ->assertNoContent();

        $this->assertDatabaseHas('analytics_events', ['source' => 'android']);
    }

    public function test_accepts_a_full_100_event_batch(): void
    {
        $events = [];
        for ($i = 1; $i <= 100; $i++) {
            $events[] = [
                'event_type' => 'devotional.viewed',
                'subject_type' => 'devotional',
                'subject_id' => $i,
                'occurred_at' => '2026-05-06T12:00:00Z',
            ];
        }

        $this->withHeaders($this->apiKeyHeaders() + ['User-Agent' => 'Mozilla/5.0 Chrome/100'])
            ->postJson(route('analytics.events.store'), [
                'events' => $events,
                'device_id' => 'd1',
            ])
            ->assertNoContent();

        $this->assertSame(100, AnalyticsEvent::query()->count());
    }

    public function test_rejects_a_101_event_batch(): void
    {
        $events = [];
        for ($i = 1; $i <= 101; $i++) {
            $events[] = [
                'event_type' => 'devotional.viewed',
                'subject_type' => 'devotional',
                'subject_id' => $i,
                'occurred_at' => '2026-05-06T12:00:00Z',
            ];
        }

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('analytics.events.store'), [
                'events' => $events,
                'device_id' => 'd1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['events']);
    }

    public function test_rejects_unknown_event_type(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('analytics.events.store'), [
                'events' => [[
                    'event_type' => 'bogus.event',
                    'occurred_at' => '2026-05-06T12:00:00Z',
                ]],
                'device_id' => 'd1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['events.0.event_type']);
    }

    public function test_rejects_subjectful_event_without_subject(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('analytics.events.store'), [
                'events' => [[
                    'event_type' => 'devotional.viewed',
                    'occurred_at' => '2026-05-06T12:00:00Z',
                ]],
                'device_id' => 'd1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'events.0.subject_type',
                'events.0.subject_id',
            ]);
    }

    public function test_rejects_chapter_view_without_required_metadata(): void
    {
        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('analytics.events.store'), [
                'events' => [[
                    'event_type' => 'bible.chapter.viewed',
                    'subject_type' => 'bible_chapter',
                    'subject_id' => 1,
                    'occurred_at' => '2026-05-06T12:00:00Z',
                ]],
                'device_id' => 'd1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['events.0.metadata']);
    }

    public function test_rate_limiter_blocks_after_600_requests_per_window(): void
    {
        Queue::fake();

        // Pre-saturate the limiter bucket using the same key the
        // throttle middleware hashes (`analytics-ingest` + ip|device).
        $bucketKey = md5('analytics-ingest127.0.0.1|d1');
        for ($i = 0; $i < 600; $i++) {
            RateLimiter::hit($bucketKey, 60);
        }

        $payload = [
            'events' => [[
                'event_type' => 'devotional.viewed',
                'subject_type' => 'devotional',
                'subject_id' => 1,
                'occurred_at' => '2026-05-06T12:00:00Z',
            ]],
            'device_id' => 'd1',
        ];

        $this->withHeaders($this->apiKeyHeaders() + ['User-Agent' => 'Mozilla/5.0 Chrome/100'])
            ->postJson(route('analytics.events.store'), $payload)
            ->assertStatus(429);
    }
}
