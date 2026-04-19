<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\FinishReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Http\Requests\ReadingPlans\FinishReadingPlanSubscriptionRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;

/**
 * @tags Reading Plans
 */
final class FinishReadingPlanSubscriptionController
{
    /**
     * Finish a subscription.
     *
     * Marks the authenticated user's reading plan subscription as finished.
     * Requires all days to be completed; otherwise the request is rejected.
     */
    public function __invoke(
        FinishReadingPlanSubscriptionRequest $request,
        ReadingPlanSubscription $subscription,
        FinishReadingPlanSubscriptionAction $action,
    ): ReadingPlanSubscriptionResource {
        return ReadingPlanSubscriptionResource::make($action->execute($subscription));
    }
}
