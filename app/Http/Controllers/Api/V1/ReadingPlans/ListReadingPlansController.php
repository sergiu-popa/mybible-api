<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Http\Requests\ReadingPlans\ListReadingPlansRequest;
use App\Http\Resources\ReadingPlans\ReadingPlanResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListReadingPlansController
{
    public function __invoke(ListReadingPlansRequest $request): AnonymousResourceCollection
    {
        $plans = ReadingPlan::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate($request->perPage());

        return ReadingPlanResource::collection($plans);
    }
}
