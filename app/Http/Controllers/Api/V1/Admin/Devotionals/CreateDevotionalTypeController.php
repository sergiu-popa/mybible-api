<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Devotionals;

use App\Domain\Devotional\Actions\CreateDevotionalTypeAction;
use App\Http\Requests\Admin\Devotionals\CreateDevotionalTypeRequest;
use App\Http\Resources\Devotionals\DevotionalTypeResource;
use Illuminate\Http\JsonResponse;

final class CreateDevotionalTypeController
{
    public function __invoke(
        CreateDevotionalTypeRequest $request,
        CreateDevotionalTypeAction $action,
    ): JsonResponse {
        $type = $action->handle($request->toData());

        return DevotionalTypeResource::make($type)
            ->response()
            ->setStatusCode(201);
    }
}
