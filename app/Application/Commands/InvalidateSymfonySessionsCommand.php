<?php

declare(strict_types=1);

namespace App\Application\Commands;

use App\Domain\Security\Actions\InvalidateAllSymfonySessionsAction;
use App\Domain\Security\Exceptions\SymfonyCutoverAlreadyExecutedException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Cutover-time artisan entry point for
 * {@see InvalidateAllSymfonySessionsAction}.
 *
 * Usage during cutover:
 *
 *     php artisan mybible:invalidate-symfony-sessions \
 *         --cutover-at="2026-05-01T03:00:00+03:00"
 *
 * `--dry-run` prints the would-be affected count without mutating
 * anything — intended for the staging dry-run rehearsal.
 */
final class InvalidateSymfonySessionsCommand extends Command
{
    protected $signature = 'mybible:invalidate-symfony-sessions
        {--cutover-at= : ISO-8601 cutover timestamp. Tokens created strictly before this moment are revoked. Defaults to now().}
        {--dry-run : Report the affected count without deleting tokens or writing the audit row.}
        {--reason= : Override the audit-row justification string.}';

    protected $description = 'Revoke all Sanctum tokens issued before cutover and write the security_events audit row (one-shot).';

    public function handle(InvalidateAllSymfonySessionsAction $action): int
    {
        $cutoverAtOptionRaw = $this->option('cutover-at');
        $cutoverAtOption = is_string($cutoverAtOptionRaw) ? $cutoverAtOptionRaw : null;
        $reasonOptionRaw = $this->option('reason');
        $reasonOption = is_string($reasonOptionRaw) ? $reasonOptionRaw : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $cutoverAt = $cutoverAtOption !== null && $cutoverAtOption !== ''
                ? Carbon::parse($cutoverAtOption)
                : Carbon::now();
        } catch (Throwable) {
            $this->error("Invalid --cutover-at value: {$cutoverAtOption}");

            return self::INVALID;
        }

        try {
            $result = $action->execute(
                cutoverAt: $cutoverAt,
                reason: $reasonOption !== null && $reasonOption !== ''
                    ? $reasonOption
                    : 'Symfony → Laravel API cutover forced logout.',
                dryRun: $dryRun,
            );
        } catch (SymfonyCutoverAlreadyExecutedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->line(sprintf(
                '[dry-run] would revoke %d token(s) created before %s.',
                $result['affected_count'],
                $result['cutover_at'],
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Revoked %d token(s) created before %s. security_events id=%d.',
            $result['affected_count'],
            $result['cutover_at'],
            (int) $result['event_id'],
        ));

        return self::SUCCESS;
    }
}
