<?php

declare(strict_types=1);

namespace App\Domain\Migration\Etl\DataTransferObjects;

/**
 * Knobs surfaced by `php artisan symfony:etl` that flow into the
 * orchestrator. `only` restricts the chain to a list of sub-job slugs;
 * `resume` filters out sub-jobs that already reached a terminal state.
 */
final readonly class EtlRunOptions
{
    /**
     * @param  list<string>  $only
     */
    public function __construct(
        public bool $confirm = false,
        public bool $dryRun = false,
        public bool $resume = false,
        public array $only = [],
    ) {}

    public function shouldRun(string $slug): bool
    {
        if ($this->only === []) {
            return true;
        }

        return in_array($slug, $this->only, true);
    }
}
