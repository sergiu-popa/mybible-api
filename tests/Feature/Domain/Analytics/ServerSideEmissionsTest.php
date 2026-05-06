<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Analytics;

use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\QrCode\Models\QrCode;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAuthentication;
use Tests\Concerns\InteractsWithReadingPlans;
use Tests\Concerns\WithApiKeyClient;
use Tests\TestCase;

final class ServerSideEmissionsTest extends TestCase
{
    use InteractsWithAuthentication;
    use InteractsWithReadingPlans;
    use RefreshDatabase;
    use WithApiKeyClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpApiKeyClient();
    }

    public function test_login_emits_auth_login_event(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'jane@example.com',
            'password' => 'secret-pass',
        ])->assertOk();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'auth.login')->count(),
        );
    }

    public function test_qr_code_scan_emits_event(): void
    {
        $qr = QrCode::factory()->create();

        $this->withHeaders($this->apiKeyHeaders())
            ->postJson(route('qr-codes.scans.store', ['qr' => $qr->id]))
            ->assertNoContent();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'qr_code.scanned')->count(),
        );
    }

    public function test_resource_download_emits_event(): void
    {
        $resource = EducationalResource::factory()->create();

        $this->withHeaders($this->apiKeyHeaders() + ['X-Device-Id' => 'd1'])
            ->postJson(route('resources.downloads.store', ['resource' => $resource->uuid]))
            ->assertNoContent();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'resource.downloaded')->count(),
        );
    }

    public function test_resource_book_download_emits_event(): void
    {
        $book = ResourceBook::factory()->published()->create();

        $this->withHeaders($this->apiKeyHeaders() + ['X-Device-Id' => 'd1'])
            ->postJson(route('resource-books.downloads.store', ['book' => $book->slug]))
            ->assertNoContent();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'resource_book.downloaded')->count(),
        );
    }

    public function test_resource_book_chapter_download_emits_event(): void
    {
        $book = ResourceBook::factory()->published()->create();
        $chapter = ResourceBookChapter::factory()->forBook($book)->create(['position' => 1]);

        $this->withHeaders($this->apiKeyHeaders() + ['X-Device-Id' => 'd1'])
            ->postJson(route('resource-books.chapters.downloads.store', [
                'book' => $book->slug,
                'chapter' => $chapter->id,
            ]))
            ->assertNoContent();

        $this->assertSame(
            1,
            AnalyticsEvent::query()->where('event_type', 'resource_book.chapter.downloaded')->count(),
        );
    }

    public function test_reading_plan_started_emits_event_with_plan_metadata(): void
    {
        Carbon::setTestNow('2026-05-01 08:00:00');

        $plan = $this->givenAPublishedReadingPlanWithDays(2);
        $this->givenAnAuthenticatedUser();

        $this->postJson(route('reading-plans.subscriptions.store', ['plan' => $plan->slug]))
            ->assertCreated();

        $event = AnalyticsEvent::query()
            ->where('event_type', 'reading_plan.subscription.started')
            ->firstOrFail();

        $this->assertSame($plan->id, $event->metadata['plan_id'] ?? null);
        $this->assertSame($plan->slug, $event->metadata['plan_slug'] ?? null);

        Carbon::setTestNow();
    }

    public function test_reading_plan_day_completed_emits_event_with_plan_metadata(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');

        $plan = $this->givenAPublishedReadingPlanWithDays(1);
        $planDay = $plan->days()->firstOrFail();
        $user = User::factory()->create();
        $subscription = $this->givenAnActiveSubscriptionTo($plan, $user);
        $day = $this->givenASubscriptionDay($subscription, $planDay, ['completed_at' => null]);

        $this->givenAnAuthenticatedUser($user);

        $this->postJson(route('reading-plan-subscriptions.days.complete', [
            'subscription' => $subscription->id,
            'day' => $day->id,
        ]))->assertOk();

        $event = AnalyticsEvent::query()
            ->where('event_type', 'reading_plan.subscription.day_completed')
            ->firstOrFail();

        $this->assertSame($plan->id, $event->metadata['plan_id'] ?? null);
        $this->assertSame($plan->slug, $event->metadata['plan_slug'] ?? null);
        $this->assertSame(1, $event->metadata['day_position'] ?? null);

        Carbon::setTestNow();
    }

    public function test_reading_plan_abandoned_emits_event_with_plan_metadata(): void
    {
        $user = User::factory()->create();
        $plan = $this->givenAPublishedReadingPlanWithDays(2);
        $subscription = $this->givenAnActiveSubscriptionTo($plan, $user);

        $this->givenAnAuthenticatedUser($user);

        $this->postJson(route('reading-plan-subscriptions.abandon', [
            'subscription' => $subscription->id,
        ]))->assertOk();

        $event = AnalyticsEvent::query()
            ->where('event_type', 'reading_plan.subscription.abandoned')
            ->firstOrFail();

        $this->assertSame($plan->id, $event->metadata['plan_id'] ?? null);
        $this->assertSame($plan->slug, $event->metadata['plan_slug'] ?? null);
    }

    public function test_reading_plan_finished_emits_event_with_plan_metadata(): void
    {
        Carbon::setTestNow('2026-05-10 14:30:00');

        $user = User::factory()->create();
        $plan = $this->givenAPublishedReadingPlan();
        $subscription = $this->givenAnActiveSubscriptionTo($plan, $user);

        $planDay = ReadingPlanDay::factory()->create([
            'reading_plan_id' => $plan->id,
            'position' => 1,
        ]);
        $this->givenASubscriptionDay($subscription, $planDay, [
            'completed_at' => Carbon::parse('2026-04-15 10:00:00'),
        ]);

        $this->givenAnAuthenticatedUser($user);

        $this->postJson(route('reading-plan-subscriptions.finish', [
            'subscription' => $subscription->id,
        ]))->assertOk();

        $event = AnalyticsEvent::query()
            ->where('event_type', 'reading_plan.subscription.completed')
            ->firstOrFail();

        $this->assertSame($plan->id, $event->metadata['plan_id'] ?? null);
        $this->assertSame($plan->slug, $event->metadata['plan_slug'] ?? null);

        Carbon::setTestNow();
    }
}
