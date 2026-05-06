<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Domain\Analytics\Actions\RecordAnalyticsEventBatchAction;
use App\Http\Requests\Analytics\IngestAnalyticsEventsRequest;
use Illuminate\Http\Response;

final class IngestAnalyticsEventsController
{
    public function __invoke(
        IngestAnalyticsEventsRequest $request,
        RecordAnalyticsEventBatchAction $action,
    ): Response {
        $action->execute($request->toBatchData());

        return response()->noContent();
    }
}
