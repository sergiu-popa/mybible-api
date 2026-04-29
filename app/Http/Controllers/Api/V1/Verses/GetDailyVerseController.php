<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Verses;

use App\Domain\Bible\Support\BibleCacheHeaders;
use App\Domain\Verses\Actions\GetDailyVerseAction;
use App\Http\Requests\Verses\DailyVerseRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Verses
 */
final class GetDailyVerseController
{
    public function __invoke(
        DailyVerseRequest $request,
        GetDailyVerseAction $action,
    ): JsonResponse {
        $payload = $action->handle($request->forDate());

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=' . BibleCacheHeaders::DAILY_VERSE_MAX_AGE);
    }
}
