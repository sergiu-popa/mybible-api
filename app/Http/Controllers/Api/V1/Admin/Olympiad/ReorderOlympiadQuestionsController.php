<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Olympiad;

use App\Domain\Olympiad\Actions\ReorderOlympiadQuestionsAction;
use App\Http\Requests\Admin\Olympiad\ReorderOlympiadQuestionsRequest;
use Illuminate\Http\JsonResponse;

final class ReorderOlympiadQuestionsController
{
    public function __invoke(
        ReorderOlympiadQuestionsRequest $request,
        ReorderOlympiadQuestionsAction $action,
    ): JsonResponse {
        $action->execute(
            $request->book(),
            $request->range(),
            $request->language(),
            $request->ids(),
        );

        return response()->json(['message' => 'Reordered.']);
    }
}
