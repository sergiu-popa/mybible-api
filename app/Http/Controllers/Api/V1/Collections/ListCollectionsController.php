<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collections;

use App\Domain\Collections\Actions\ListCollectionsAction;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Collections\ListCollectionsRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Collections
 */
final class ListCollectionsController
{
    public function __invoke(
        ListCollectionsRequest $request,
        ListCollectionsAction $action,
    ): JsonResponse {
        $resolved = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);
        $language = $resolved instanceof Language ? $resolved : Language::En;

        $page = max(1, (int) $request->query('page', '1'));

        return response()->json($action->handle($language, $page, $request->perPage()));
    }
}
