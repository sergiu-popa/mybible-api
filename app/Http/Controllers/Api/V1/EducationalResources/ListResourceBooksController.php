<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\EducationalResources\Actions\ListResourceBooksAction;
use App\Http\Requests\EducationalResources\ListResourceBooksRequest;
use Illuminate\Http\JsonResponse;

final class ListResourceBooksController
{
    public function __invoke(
        ListResourceBooksRequest $request,
        ListResourceBooksAction $action,
    ): JsonResponse {
        $payload = $action->execute(
            $request->languageFilter(),
            $request->pageNumber(),
            15,
        );

        return response()->json($payload);
    }
}
