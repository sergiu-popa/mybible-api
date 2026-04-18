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
        $query = ReadingPlan::query()
            ->published()
            ->orderByDesc('published_at');

        $user = $request->user();

        if ($user !== null) {
            $query->with([
                'subscriptions' => fn ($q) => $q
                    ->forUser($user)
                    ->withProgressCounts(),
            ]);
        }

        return ReadingPlanResource::collection($query->paginate($request->perPage()));
    }
}
