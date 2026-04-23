<?php

declare(strict_types=1);

namespace App\Domain\Security\Actions;

use App\Domain\Security\Exceptions\SymfonyCutoverAlreadyExecutedException;
use App\Domain\Security\Models\SecurityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One-shot cutover operation: revokes every Sanctum personal access
 * token issued before the cutover timestamp and writes a single
 * audit row to `security_events`.
 *
 * Invariants enforced:
 *  - Runs exactly once. A second invocation throws
 *    {@see SymfonyCutoverAlreadyExecutedException} so ops never writes
 *    a duplicate audit row or revokes tokens issued legitimately after
 *    the first run.
 *  - Writes the audit row and deletes the tokens in a single DB
 *    transaction so a partial failure leaves no orphaned state.
 *  - `dryRun` mode returns the count that would be affected without
 *    mutating anything.
 */
final class InvalidateAllSymfonySessionsAction
{
    /**
     * @return array{affected_count: int, event_id: int|null, cutover_at: string}
     */
    public function execute(
        Carbon $cutoverAt,
        string $reason = 'Symfony → Laravel API cutover forced logout.',
        bool $dryRun = false,
    ): array {
        $this->guardAgainstDoubleRun();

        $affectedQuery = PersonalAccessToken::query()->where('created_at', '<', $cutoverAt);
        $affectedCount = $affectedQuery->count();

        if ($dryRun) {
            return [
                'affected_count' => $affectedCount,
                'event_id' => null,
                'cutover_at' => $cutoverAt->toIso8601String(),
            ];
        }

        /** @var SecurityEvent $event */
        $event = DB::transaction(function () use ($cutoverAt, $reason, $affectedCount): SecurityEvent {
            PersonalAccessToken::query()
                ->where('created_at', '<', $cutoverAt)
                ->delete();

            return SecurityEvent::query()->create([
                'event' => SecurityEvent::EVENT_SYMFONY_CUTOVER_FORCED_LOGOUT,
                'reason' => $reason,
                'affected_count' => $affectedCount,
                'metadata' => [
                    'cutover_at' => $cutoverAt->toIso8601String(),
                ],
                'occurred_at' => Carbon::now(),
            ]);
        });

        return [
            'affected_count' => $affectedCount,
            'event_id' => $event->id,
            'cutover_at' => $cutoverAt->toIso8601String(),
        ];
    }

    private function guardAgainstDoubleRun(): void
    {
        $alreadyRan = SecurityEvent::query()
            ->where('event', SecurityEvent::EVENT_SYMFONY_CUTOVER_FORCED_LOGOUT)
            ->exists();

        if ($alreadyRan) {
            throw new SymfonyCutoverAlreadyExecutedException;
        }
    }
}
