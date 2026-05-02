<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\CreateCommentaryTextAction;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\CreateCommentaryTextRequest;
use App\Http\Resources\Commentary\AdminCommentaryTextResource;
use Illuminate\Http\JsonResponse;

final class CreateCommentaryTextController
{
    public function __invoke(
        CreateCommentaryTextRequest $request,
        Commentary $commentary,
        CreateCommentaryTextAction $action,
    ): JsonResponse {
        $text = $action->execute($commentary, $request->toData());

        return AdminCommentaryTextResource::make($text)
            ->response()
            ->setStatusCode(201);
    }
}
