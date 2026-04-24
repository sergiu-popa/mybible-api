<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Commands;

use App\Domain\Security\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

/**
 * Uses `Artisan::call(...)` rather than `$this->artisan(...)` because
 * PendingCommand runs the command in a fashion that does not commit
 * savepoints created by nested `DB::transaction()` calls under the
 * RefreshDatabase outer transaction — changes made inside the
 * command's transaction are visible inside the command but rolled
 * back before the assertions run. `Artisan::call()` runs the command
 * inline in the same transactional scope as the test body, which is
 * what we actually want to assert against.
 */
final class InvalidateSymfonySessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_mutate_the_database(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-05-01T02:00:00+00:00');
        $user->createToken('pre');

        Carbon::setTestNow('2026-05-01T04:00:00+00:00');

        $exitCode = Artisan::call('mybible:invalidate-symfony-sessions', [
            '--cutover-at' => '2026-05-01T03:00:00+00:00',
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('[dry-run] would revoke 1 token(s)', Artisan::output());

        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->assertSame(0, SecurityEvent::query()->count());
    }

    public function test_non_dry_run_revokes_tokens_and_writes_the_audit_row(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-05-01T02:00:00+00:00');
        $user->createToken('pre');

        Carbon::setTestNow('2026-05-01T04:00:00+00:00');

        $this->assertSame(1, PersonalAccessToken::query()->count(), 'pre-condition: one token exists');

        $exitCode = Artisan::call('mybible:invalidate-symfony-sessions', [
            '--cutover-at' => '2026-05-01T03:00:00+00:00',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Revoked 1 token(s)', Artisan::output());

        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEvent::EVENT_SYMFONY_CUTOVER_FORCED_LOGOUT,
            'affected_count' => 1,
        ]);
    }

    public function test_second_invocation_exits_with_failure_and_does_not_write_another_event(): void
    {
        User::factory()->create();

        $firstExit = Artisan::call('mybible:invalidate-symfony-sessions');
        $this->assertSame(Command::SUCCESS, $firstExit);

        $secondExit = Artisan::call('mybible:invalidate-symfony-sessions');
        $this->assertSame(Command::FAILURE, $secondExit);
        $this->assertStringContainsString('already been executed', Artisan::output());

        $this->assertSame(1, SecurityEvent::query()
            ->where('event', SecurityEvent::EVENT_SYMFONY_CUTOVER_FORCED_LOGOUT)
            ->count());
    }

    public function test_invalid_cutover_at_value_returns_invalid_exit_code(): void
    {
        $exitCode = Artisan::call('mybible:invalidate-symfony-sessions', [
            '--cutover-at' => 'not-a-date',
        ]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('Invalid --cutover-at value', Artisan::output());
        $this->assertSame(0, SecurityEvent::query()->count());
    }
}
