<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Verses;

use App\Domain\Verses\Actions\GetDailyVerseAction;
use App\Http\Requests\Verses\DailyVerseRequest;
use App\Http\Resources\Verses\DailyVerseResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Verses
 */
final class GetDailyVerseController
{
    private const MAX_AGE = 3600;

    public function __invoke(
        DailyVerseRequest $request,
        GetDailyVerseAction $action,
    ): Response {
        $dailyVerse = $action->handle($request->forDate());

        return DailyVerseResource::make($dailyVerse)
            ->response($request)
            ->header('Cache-Control', 'public, max-age=' . self::MAX_AGE);
    }
}
