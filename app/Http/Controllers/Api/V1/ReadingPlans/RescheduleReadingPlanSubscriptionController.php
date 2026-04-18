<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\RescheduleReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Http\Requests\ReadingPlans\RescheduleReadingPlanSubscriptionRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;

final class RescheduleReadingPlanSubscriptionController
{
    public function __invoke(
        RescheduleReadingPlanSubscriptionRequest $request,
        ReadingPlanSubscription $subscription,
        RescheduleReadingPlanSubscriptionAction $action,
    ): ReadingPlanSubscriptionResource {
        $subscription = $action->execute($request->toData($subscription));

        return ReadingPlanSubscriptionResource::make($subscription);
    }
}
