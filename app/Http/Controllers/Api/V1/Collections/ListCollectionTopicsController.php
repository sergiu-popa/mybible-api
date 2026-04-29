<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collections;

use App\Domain\Collections\Actions\ListCollectionTopicsAction;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Collections\ListCollectionTopicsRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Collections
 */
final class ListCollectionTopicsController
{
    public const CACHE_MAX_AGE = 3600;

    public function __invoke(
        ListCollectionTopicsRequest $request,
        ListCollectionTopicsAction $action,
    ): JsonResponse {
        $resolved = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);
        $language = $resolved instanceof Language ? $resolved : Language::En;

        $page = max(1, (int) $request->query('page', '1'));

        $payload = $action->execute($language, $page, $request->perPage());

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
