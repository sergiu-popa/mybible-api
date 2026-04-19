<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\AbandonReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Http\Requests\ReadingPlans\AbandonReadingPlanSubscriptionRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;

/**
 * @tags Reading Plan Subscriptions
 */
final class AbandonReadingPlanSubscriptionController
{
    /**
     * Abandon a subscription.
     *
     * Marks the authenticated user's reading plan subscription as abandoned.
     * The subscription is preserved for history but no longer counts as
     * active and cannot be resumed.
     */
    public function __invoke(
        AbandonReadingPlanSubscriptionRequest $request,
        ReadingPlanSubscription $subscription,
        AbandonReadingPlanSubscriptionAction $action,
    ): ReadingPlanSubscriptionResource {
        return ReadingPlanSubscriptionResource::make($action->execute($subscription));
    }
}
