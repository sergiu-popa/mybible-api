<?php

declare(strict_types=1);

namespace App\Domain\Migration\Etl\DataTransferObjects;

/**
 * Aggregated counts emitted by a single ETL sub-job. The orchestrator and
 * `EtlJobReporter` consume this to populate the corresponding `import_jobs`
 * row.
 *
 * @phpstan-type ErrorSample array{row?: int|string, message: string}
 */
final readonly class EtlSubJobResult
{
    /**
     * @param  list<ErrorSample>  $errors
     */
    public function __construct(
        public int $processed = 0,
        public int $succeeded = 0,
        public int $skipped = 0,
        public array $errors = [],
    ) {}

    public function isPartial(): bool
    {
        return count($this->errors) > 0 && $this->succeeded > 0;
    }

    public function isFailed(): bool
    {
        return count($this->errors) > 0 && $this->succeeded === 0 && $this->processed > 0;
    }

    /**
     * @return array{processed: int, succeeded: int, skipped: int, error_count: int, errors: list<ErrorSample>}
     */
    public function toPayload(): array
    {
        return [
            'processed' => $this->processed,
            'succeeded' => $this->succeeded,
            'skipped' => $this->skipped,
            'error_count' => count($this->errors),
            // Cap errors to first 50 to keep payload bounded.
            'errors' => array_slice($this->errors, 0, 50),
        ];
    }
}
