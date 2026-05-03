<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\CreateResourceBookAction;
use App\Http\Requests\Admin\EducationalResources\CreateResourceBookRequest;
use App\Http\Resources\EducationalResources\AdminResourceBookResource;
use Illuminate\Http\JsonResponse;

final class CreateResourceBookController
{
    public function __invoke(
        CreateResourceBookRequest $request,
        CreateResourceBookAction $action,
    ): JsonResponse {
        $book = $action->execute($request->toData());

        return AdminResourceBookResource::make($book)
            ->response()
            ->setStatusCode(201);
    }
}
