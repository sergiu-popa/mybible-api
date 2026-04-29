<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Olympiad;

use App\Domain\Olympiad\Actions\ListOlympiadThemesAction;
use App\Http\Requests\Olympiad\ListOlympiadThemesRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Olympiad
 */
final class ListOlympiadThemesController
{
    private const CACHE_CONTROL = 'public, max-age=3600';

    /**
     * List olympiad themes.
     *
     * Returns a paginated list of distinct theme tuples
     * (`book`, `chapters_from`, `chapters_to`, `language`) with their
     * question counts, filtered by the resolved request language.
     */
    public function __invoke(
        ListOlympiadThemesRequest $request,
        ListOlympiadThemesAction $action,
    ): JsonResponse {
        $page = max(1, (int) $request->query('page', '1'));

        $payload = $action->execute($request->toFilter(), $page);

        return response()->json($payload)
            ->header('Cache-Control', self::CACHE_CONTROL);
    }
}
