<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Olympiad;

use App\Domain\Olympiad\Actions\ListOlympiadThemesAction;
use App\Http\Requests\Olympiad\ListOlympiadThemesRequest;
use App\Http\Resources\Olympiad\OlympiadThemeResource;
use Symfony\Component\HttpFoundation\Response;

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
    ): Response {
        $paginator = $action->execute($request->toFilter());

        return OlympiadThemeResource::collection($paginator)
            ->response($request)
            ->header('Cache-Control', self::CACHE_CONTROL);
    }
}
