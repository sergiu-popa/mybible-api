<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ShowTrimesterAction;
use App\Http\Requests\SabbathSchool\ShowSabbathSchoolTrimesterRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Sabbath School
 */
final class ShowSabbathSchoolTrimesterController
{
    private const CACHE_MAX_AGE = 3600;

    public function __invoke(
        ShowSabbathSchoolTrimesterRequest $request,
        ShowTrimesterAction $action,
    ): JsonResponse {
        $trimesterId = (int) $request->route('trimester');

        $payload = $action->execute($trimesterId, $request->resolvedLanguage());

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
