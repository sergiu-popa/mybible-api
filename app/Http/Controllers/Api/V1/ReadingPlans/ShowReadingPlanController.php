<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Http\Requests\ReadingPlans\ShowReadingPlanRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanResource;

/**
 * @tags Reading Plans
 */
final class ShowReadingPlanController
{
    /**
     * Show a reading plan.
     *
     * Returns a single reading plan resolved by slug, eagerly loading days and
     * their fragments. When the request is authenticated, the user's own
     * subscriptions for this plan (with progress counts) are also embedded.
     */
    public function __invoke(ShowReadingPlanRequest $request, ReadingPlan $plan): ReadingPlanResource
    {
        $plan->load(['days.fragments']);

        $user = $request->user();

        if ($user !== null) {
            $plan->load([
                'subscriptions' => fn ($query) => $query
                    ->forUser($user)
                    ->withProgressCounts(),
            ]);
        }

        return ReadingPlanResource::make($plan);
    }
}
