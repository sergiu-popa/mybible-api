<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\FinishReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Http\Requests\ReadingPlans\FinishReadingPlanSubscriptionRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;

final class FinishReadingPlanSubscriptionController
{
    public function __invoke(
        FinishReadingPlanSubscriptionRequest $request,
        ReadingPlanSubscription $subscription,
        FinishReadingPlanSubscriptionAction $action,
    ): ReadingPlanSubscriptionResource {
        return ReadingPlanSubscriptionResource::make($action->execute($subscription));
    }
}
