<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collections;

use App\Domain\Collections\Actions\ResolveCollectionReferencesAction;
use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Collections\ShowCollectionTopicRequest;
use App\Http\Resources\Collections\CollectionTopicDetailResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Collections
 */
final class ShowCollectionTopicController
{
    public const CACHE_MAX_AGE = 3600;

    public function __invoke(
        ShowCollectionTopicRequest $request,
        CollectionTopic $topic,
        ResolveCollectionReferencesAction $action,
    ): Response {
        $topic->load('references');

        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);
        $language = $language instanceof Language ? $language : Language::En;

        $resolved = $action->handle($topic->references, $language);

        return (new CollectionTopicDetailResource($topic, $resolved))
            ->response($request)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
