<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\CompleteReadingPlanSubscriptionDayAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Http\Requests\ReadingPlans\CompleteReadingPlanSubscriptionDayRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionDayResource;

final class CompleteReadingPlanSubscriptionDayController
{
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
