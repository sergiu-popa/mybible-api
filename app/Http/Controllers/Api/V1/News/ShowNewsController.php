<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\News;

use App\Domain\News\Actions\ShowNewsAction;
use App\Domain\News\Models\News;
use App\Http\Requests\News\ShowNewsRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags News
 */
final class ShowNewsController
{
    public function __invoke(
        ShowNewsRequest $request,
        News $news,
        ShowNewsAction $action,
    ): JsonResponse {
        return response()->json($action->execute($news));
    }
}
