<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Actions\StartReadingPlanSubscriptionAction;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Http\Requests\ReadingPlans\StartReadingPlanSubscriptionRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanSubscriptionResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Reading Plan Subscriptions
 */
final class StartReadingPlanSubscriptionController
{
    /**
     * Start a reading plan subscription.
     *
     * Creates a new subscription for the authenticated user on the given
     * reading plan, scheduled from the provided start date. Returns the
     * created subscription with its generated day schedule.
     */
    public function __invoke(
        StartReadingPlanSubscriptionRequest $request,
        ReadingPlan $plan,
        StartReadingPlanSubscriptionAction $action,
    ): JsonResponse {
        $plan->load('days');

        $subscription = $action->execute($request->toData($plan));

        return ReadingPlanSubscriptionResource::make($subscription)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
