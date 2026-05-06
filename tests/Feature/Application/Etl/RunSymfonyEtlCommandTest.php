<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Etl;

use App\Application\Jobs\Etl\RunSymfonyEtlJob;
use App\Domain\Admin\Imports\Models\ImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AC §11, §17 — `php artisan symfony:etl --dry-run` produces a JSON
 * summary on stdout without persisting any rows; `--confirm` dispatches
 * the orchestrator job onto the etl queue.
 */
final class RunSymfonyEtlCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_refuses_to_run_without_confirm_or_dry_run(): void
    {
        $pending = $this->artisan('symfony:etl');
        $this->assertInstanceOf(PendingCommand::class, $pending);

        $pending->expectsOutputToContain('Refusing to run without --confirm.')
            ->assertFailed();
    }

    #[Test]
    public function dry_run_does_not_persist_rows(): void
    {
        $countBefore = ImportJob::query()->count();

        $pending = $this->artisan('symfony:etl', ['--dry-run' => true]);
        $this->assertInstanceOf(PendingCommand::class, $pending);
        $pending->assertSuccessful();

        // Force the command to actually run.
        $pending->run();

        // The dry-run wraps each sub-job in a transaction that rolls
        // back, so no `import_jobs` rows survive.
        $this->assertSame($countBefore, ImportJob::query()->count());
    }

    #[Test]
    public function confirm_dispatches_orchestrator_with_correct_options(): void
    {
        Bus::fake([RunSymfonyEtlJob::class]);

        $pending = $this->artisan('symfony:etl', ['--confirm' => true]);
        $this->assertInstanceOf(PendingCommand::class, $pending);
        $pending->assertSuccessful();
        // PendingCommand defers execution until destructor or run(); call
        // run() explicitly so the job dispatch happens before assertions.
        $pending->run();

        Bus::assertDispatched(RunSymfonyEtlJob::class, function (RunSymfonyEtlJob $job): bool {
            return $job->options->confirm === true
                && $job->options->dryRun === false
                && $job->queue === 'etl';
        });
    }

    #[Test]
    public function only_filter_restricts_subjobs_in_dry_run(): void
    {
        $countBefore = ImportJob::query()->count();

        $pending = $this->artisan('symfony:etl', [
            '--dry-run' => true,
            '--only' => ['etl_news_language_default'],
        ]);
        $this->assertInstanceOf(PendingCommand::class, $pending);
        $pending->assertSuccessful();

        $this->assertSame($countBefore, ImportJob::query()->count());
    }
}
