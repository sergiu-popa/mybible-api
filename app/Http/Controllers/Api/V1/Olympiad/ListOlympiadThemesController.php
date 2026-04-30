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
     * # Aggregation contract
     *
     * A theme is a distinct tuple of
     * `(book, chapters_from, chapters_to, language)` present in
     * `olympiad_questions`. The list groups by that tuple, projects
     * `question_count = COUNT(*)`, and orders by
     * `language ASC, book ASC, chapters_from ASC, chapters_to ASC` so
     * pagination is stable across calls.
     *
     * Filters: only the resolved request language is honoured server-
     * side; book / chapter range filters are not exposed on the list
     * endpoint by design — clients drive into a specific theme via
     * `GET /olympiad/themes/{book}/{chapters}`. Pagination uses the
     * standard `?page=` + `per_page` query params resolved by
     * `ListOlympiadThemesRequest`.
     *
     * Response cached publicly for one hour. The cache is keyed by
     * `(language, page, per_page)` and tagged so writes against
     * `olympiad_questions` invalidate every theme list page in lockstep.
     *
     * The admin should *not* re-implement this aggregation client-side:
     * compute counts and ordering server-side, then render directly.
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
