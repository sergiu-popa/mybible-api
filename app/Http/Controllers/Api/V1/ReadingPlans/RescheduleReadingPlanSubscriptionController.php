<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\RescheduleReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Http\Requests\ReadingPlans\RescheduleReadingPlanSubscriptionRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;

/**
 * @tags Reading Plans
 */
final class RescheduleReadingPlanSubscriptionController
{
    /**
     * Reschedule a subscription.
     *
     * Updates the start date of the authenticated user's reading plan
     * subscription and regenerates the day schedule from the new start date.
     */
    public function __invoke(
        RescheduleReadingPlanSubscriptionRequest $request,
        ReadingPlanSubscription $subscription,
        RescheduleReadingPlanSubscriptionAction $action,
    ): ReadingPlanSubscriptionResource {
        $subscription = $action->execute($request->toData($subscription));

        return ReadingPlanSubscriptionResource::make($subscription);
    }
}
