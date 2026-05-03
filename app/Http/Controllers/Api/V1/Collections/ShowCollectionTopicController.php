<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collections;

use App\Domain\Collections\Actions\ShowCollectionTopicAction;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Collections\ShowCollectionTopicRequest;
use Illuminate\Http\JsonResponse;

/**
 * @tags Collections
 */
final class ShowCollectionTopicController
{
    public const CACHE_MAX_AGE = 3600;

    public function __invoke(
        ShowCollectionTopicRequest $request,
        Collection $collection,
        CollectionTopic $topic,
        ShowCollectionTopicAction $action,
    ): JsonResponse {
        $resolved = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);
        $language = $resolved instanceof Language ? $resolved : Language::En;

        $payload = $action->execute($topic, $language);

        return response()->json($payload)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
