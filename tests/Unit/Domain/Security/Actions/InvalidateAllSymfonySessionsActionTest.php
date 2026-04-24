<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Security\Actions;

use App\Domain\Security\Actions\InvalidateAllSymfonySessionsAction;
use App\Domain\Security\Exceptions\SymfonyCutoverAlreadyExecutedException;
use App\Domain\Security\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class InvalidateAllSymfonySessionsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_revokes_tokens_created_before_cutover_and_writes_the_audit_row(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-05-01T02:00:00+00:00');
        $preCutoverToken = $user->createToken('pre-cutover');

        Carbon::setTestNow('2026-05-01T04:00:00+00:00');
        $postCutoverToken = $user->createToken('post-cutover');

        Carbon::setTestNow('2026-05-01T05:00:00+00:00');

        $cutoverAt = Carbon::parse('2026-05-01T03:00:00+00:00');

        $action = $this->app->make(InvalidateAllSymfonySessionsAction::class);
        $result = $action->execute($cutoverAt);

        $this->assertSame(1, $result['affected_count']);
        $this->assertIsInt($result['event_id']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $preCutoverToken->accessToken->getKey(),
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $postCutoverToken->accessToken->getKey(),
        ]);

        $event = SecurityEvent::query()
            ->where('event', SecurityEvent::EVENT_SYMFONY_CUTOVER_FORCED_LOGOUT)
            ->firstOrFail();
        $this->assertSame(1, $event->affected_count);
        $metadata = $event->metadata;
        $this->assertIsArray($metadata);
        $this->assertSame('2026-05-01T03:00:00+00:00', $metadata['cutover_at']);
        $this->assertNotEmpty($event->reason);
    }

    public function test_it_throws_when_invoked_a_second_time(): void
    {
        $user = User::factory()->create();
        $user->createToken('pre');

        $action = $this->app->make(InvalidateAllSymfonySessionsAction::class);
        $action->execute(Carbon::now()->addMinute());

        $this->expectException(SymfonyCutoverAlreadyExecutedException::class);

        $action->execute(Carbon::now()->addMinute());
    }

    public function test_it_does_not_revoke_tokens_created_exactly_at_the_cutover_timestamp(): void
    {
        $user = User::factory()->create();

        $cutoverAt = Carbon::parse('2026-05-01T03:00:00+00:00');

        Carbon::setTestNow($cutoverAt);
        $boundaryToken = $user->createToken('boundary');

        Carbon::setTestNow($cutoverAt->copy()->addMinute());

        $action = $this->app->make(InvalidateAllSymfonySessionsAction::class);
        $result = $action->execute($cutoverAt);

        $this->assertSame(0, $result['affected_count']);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $boundaryToken->accessToken->getKey(),
        ]);
    }

    public function test_dry_run_reports_the_count_without_mutating_anything(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-05-01T02:00:00+00:00');
        $user->createToken('pre-1');
        $user->createToken('pre-2');

        Carbon::setTestNow('2026-05-01T04:00:00+00:00');

        $cutoverAt = Carbon::parse('2026-05-01T03:00:00+00:00');

        $action = $this->app->make(InvalidateAllSymfonySessionsAction::class);
        $result = $action->execute($cutoverAt, dryRun: true);

        $this->assertSame(2, $result['affected_count']);
        $this->assertNull($result['event_id']);

        $this->assertSame(2, PersonalAccessToken::query()->count());
        $this->assertSame(0, SecurityEvent::query()->count());
    }
}
