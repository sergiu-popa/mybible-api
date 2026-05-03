<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\CreateTrimesterAction;
use App\Http\Requests\Admin\SabbathSchool\CreateTrimesterRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolTrimesterResource;
use Illuminate\Http\JsonResponse;

final class CreateTrimesterController
{
    public function __invoke(
        CreateTrimesterRequest $request,
        CreateTrimesterAction $action,
    ): JsonResponse {
        $trimester = $action->execute($request->toData());

        return SabbathSchoolTrimesterResource::make($trimester)
            ->response()
            ->setStatusCode(201);
    }
}
