<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Domain\Mobile\Actions\ShowAppBootstrapAction;
use App\Http\Requests\Mobile\ShowAppBootstrapRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Mobile
 */
final class ShowAppBootstrapController
{
    public function __invoke(ShowAppBootstrapRequest $request, ShowAppBootstrapAction $action): JsonResponse
    {
        $ttl = (int) config('mobile.bootstrap.cache_ttl', 300);

        return response()
            ->json(['data' => $action->execute($request->resolvedLanguage())])
            ->header('Cache-Control', 'public, max-age=' . $ttl);
    }
}
