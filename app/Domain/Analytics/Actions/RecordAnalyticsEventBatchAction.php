<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Application\Jobs\RecordAnalyticsEventJob;
use App\Domain\Analytics\DataTransferObjects\IngestBatchData;

/**
 * Loops a batch into individual `RecordAnalyticsEventJob` dispatches.
 * One job per event keeps retries granular: a single bad row does
 * not poison the rest of the batch.
 */
final class RecordAnalyticsEventBatchAction
{
    public function execute(IngestBatchData $batch): void
    {
        foreach ($batch->events as $event) {
            RecordAnalyticsEventJob::dispatch($event, $batch->context, $batch->appVersion);
        }
    }
}
