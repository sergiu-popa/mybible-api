<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Http\Requests\ReadingPlans\ShowReadingPlanRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ShowReadingPlanController
{
    public function __invoke(ShowReadingPlanRequest $request, string $slug): ReadingPlanResource
    {
        $plan = ReadingPlan::query()
            ->published()
            ->where('slug', $slug)
            ->withDaysAndFragments()
            ->first();

        if ($plan === null) {
            throw new ModelNotFoundException;
        }

        return ReadingPlanResource::make($plan);
    }
}
