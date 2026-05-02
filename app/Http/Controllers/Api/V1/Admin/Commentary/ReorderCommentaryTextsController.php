<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\ReorderCommentaryTextsAction;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\ReorderCommentaryTextsRequest;
use Illuminate\Http\JsonResponse;

final class ReorderCommentaryTextsController
{
    public function __invoke(
        ReorderCommentaryTextsRequest $request,
        Commentary $commentary,
        ReorderCommentaryTextsAction $action,
    ): JsonResponse {
        $action->execute($commentary, $request->book(), $request->chapter(), $request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}
