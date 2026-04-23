<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Devotionals;

use App\Domain\Devotional\Actions\FetchDevotionalAction;
use App\Http\Requests\Devotionals\ShowDevotionalRequest;
use App\Http\Resources\Devotionals\DevotionalResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Devotionals
 */
final class ShowDevotionalController
{
    private const CACHE_MAX_AGE = 3600;

    /**
     * Show the devotional for the requested language, type, and (optional)
     * date.
     *
     * The `Cache-Control: public, max-age=3600` header is intentional: the
     * response is not personalised — the same (language, type, date) tuple
     * yields the same payload for anonymous api-key callers and authenticated
     * Sanctum users alike, so shared caches can safely reuse it.
     */
    public function __invoke(
        ShowDevotionalRequest $request,
        FetchDevotionalAction $action,
    ): Response {
        $devotional = $action->execute($request->toData());

        return DevotionalResource::make($devotional)
            ->response($request)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
