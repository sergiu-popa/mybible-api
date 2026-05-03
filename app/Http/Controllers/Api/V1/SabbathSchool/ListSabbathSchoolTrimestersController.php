<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ListTrimestersAction;
use App\Http\Requests\SabbathSchool\ListSabbathSchoolTrimestersRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Sabbath School
 */
final class ListSabbathSchoolTrimestersController
{
    private const CACHE_MAX_AGE = 3600;

    public function __invoke(
        ListSabbathSchoolTrimestersRequest $request,
        ListTrimestersAction $action,
    ): JsonResponse {
        $payload = $action->execute($request->resolvedLanguage());

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
