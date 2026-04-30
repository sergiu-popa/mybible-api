<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Olympiad;

use App\Domain\Olympiad\Actions\FetchOlympiadThemeQuestionsAction;
use App\Http\Requests\Olympiad\ShowOlympiadThemeRequest;
use App\Http\Resources\Olympiad\OlympiadThemeQuestionsResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Olympiad
 */
final class ShowOlympiadThemeController
{
    private const CACHE_CONTROL = 'public, max-age=3600';

    /**
     * Show olympiad questions for a theme.
     *
     * Resolves questions + answers for a `(book, chapters_from..chapters_to,
     * language)` theme. Questions and each question's answers are shuffled
     * with a seed that the client may supply (`?seed=`) for reproducible
     * orderings; if omitted, the server generates one and echoes it back
     * under `meta.seed`.
     *
     * # Scoring / aggregation
     *
     * The endpoint returns *raw* questions and answers — `is_correct`
     * stays on each answer because the client (mobile app or admin
     * preview) renders the explanation panel itself. There is no
     * server-side scoring aggregation: scoring lives entirely on the
     * client, which is why the seed contract matters (re-shuffling
     * across reloads would break in-progress sessions). Theme-level
     * counts come from the list endpoint (see
     * {@see ListOlympiadThemesController}); do not reimplement them
     * over `/themes/{book}/{chapters}`.
     */
    public function __invoke(
        ShowOlympiadThemeRequest $request,
        FetchOlympiadThemeQuestionsAction $action,
    ): Response {
        $result = $action->execute($request->toDomainRequest());

        return (new OlympiadThemeQuestionsResource($result->questions, $result->seed))
            ->response($request)
            ->header('Cache-Control', self::CACHE_CONTROL);
    }
}
