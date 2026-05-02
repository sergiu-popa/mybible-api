<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\CreateCommentaryAction;
use App\Http\Requests\Admin\Commentary\CreateCommentaryRequest;
use App\Http\Resources\Commentary\AdminCommentaryResource;
use Illuminate\Http\JsonResponse;

final class CreateCommentaryController
{
    public function __invoke(
        CreateCommentaryRequest $request,
        CreateCommentaryAction $action,
    ): JsonResponse {
        $commentary = $action->execute($request->toData());

        return AdminCommentaryResource::make($commentary)
            ->response()
            ->setStatusCode(201);
    }
}
