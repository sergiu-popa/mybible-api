<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\CompleteReadingPlanSubscriptionDayAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Http\Requests\ReadingPlans\CompleteReadingPlanSubscriptionDayRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionDayResource;

/**
 * @tags Reading Plan Subscriptions
 */
final class CompleteReadingPlanSubscriptionDayController
{
    /**
     * Complete a subscription day.
     *
     * Marks the given day of the authenticated user's subscription as
     * completed. The {day} parameter is scoped to the parent {subscription},
     * so a day that does not belong to that subscription returns 404.
     */
    public function __invoke(
        CompleteReadingPlanSubscriptionDayRequest $request,
        // $subscription anchors the nested scopeBindings() chain so {day} is
        // resolved against its parent subscription's days relation. Removing
        // this parameter breaks the 404-on-cross-subscription guarantee.
        ReadingPlanSubscription $subscription,
        ReadingPlanSubscriptionDay $day,
        CompleteReadingPlanSubscriptionDayAction $action,
    ): ReadingPlanSubscriptionDayResource {
        $day = $action->execute($day);
        $day->load('readingPlanDay');

        return ReadingPlanSubscriptionDayResource::make($day);
    }
}
