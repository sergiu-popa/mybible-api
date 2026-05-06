<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Shared scaffolding for every Symfony→Laravel ETL sub-job.
 *
 * Lifecycle:
 *   1. Constructor pins the job onto Horizon's `etl` supervisor.
 *   2. `handle()` resolves the {@see ImportJob} ledger row, then delegates
 *      to {@see execute()} for the actual transformation.
 *   3. Result → reporter → terminal status (succeeded / partial / failed).
 *
 * Sub-jobs are idempotent by contract: re-running on already-migrated rows
 * is a no-op. The orchestrator resumes by re-dispatching, leaning on the
 * reporter's {@see EtlJobReporter::isAlreadyTerminal()} short-circuit.
 */
abstract class BaseEtlJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $importJobId)
    {
        $this->onQueue('etl');
    }

    /**
     * Stable slug used as `import_jobs.type` and as the `--only=` filter
     * value passed to the Artisan command.
     */
    abstract public static function slug(): string;

    final public function handle(EtlJobReporter $reporter): void
    {
        $importJob = ImportJob::query()->find($this->importJobId);

        if (! $importJob instanceof ImportJob) {
            return;
        }

        if ($reporter->isAlreadyTerminal($importJob)) {
            return;
        }

        try {
            $result = $this->execute($reporter, $importJob);
            $reporter->complete($importJob, $result);
        } catch (Throwable $exception) {
            $reporter->fail($importJob, $exception);

            throw $exception;
        }
    }

    abstract protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult;
}
