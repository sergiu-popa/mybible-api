<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\AbandonReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Http\Requests\ReadingPlans\AbandonReadingPlanSubscriptionRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;

final class AbandonReadingPlanSubscriptionController
{
    public function __invoke(
        AbandonReadingPlanSubscriptionRequest $request,
        ReadingPlanSubscription $subscription,
        AbandonReadingPlanSubscriptionAction $action,
    ): ReadingPlanSubscriptionResource {
        return ReadingPlanSubscriptionResource::make($action->execute($subscription));
    }
}
