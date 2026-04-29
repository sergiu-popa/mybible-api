<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\News;

use App\Domain\News\Actions\ListNewsAction;
use App\Http\Requests\News\ListNewsRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags News
 */
final class ListNewsController
{
    /**
     * List news articles.
     *
     * Returns a paginated list of published news for the resolved language,
     * newest first. `language` query parameter overrides the resolved
     * request language; defaults apply when absent.
     */
    public function __invoke(
        ListNewsRequest $request,
        ListNewsAction $action,
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', '1'));

        $payload = $action->execute(
            $request->resolvedLanguage(),
            $page,
            $request->perPage(),
        );

        return response()->json($payload);
    }
}
