<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\CompleteSubscriptionDayAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscriptionDay;
use App\Http\Requests\ReadingPlans\CompleteReadingPlanSubscriptionDayRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionDayResource;

final class CompleteReadingPlanSubscriptionDayController
{
    public function __invoke(
        CompleteReadingPlanSubscriptionDayRequest $request,
        ReadingPlanSubscription $subscription,
        ReadingPlanSubscriptionDay $day,
        CompleteSubscriptionDayAction $action,
    ): ReadingPlanSubscriptionDayResource {
        $day = $action->execute($day);
        $day->load('readingPlanDay');

        return ReadingPlanSubscriptionDayResource::make($day);
    }
}
