<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collections;

use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Collections\ListCollectionTopicsRequest;
use App\Http\Resources\Collections\CollectionTopicResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Collections
 */
final class ListCollectionTopicsController
{
    public const CACHE_MAX_AGE = 3600;

    public function __invoke(ListCollectionTopicsRequest $request): Response
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        $topics = CollectionTopic::query()
            ->forLanguage($language instanceof Language ? $language : Language::En)
            ->withReferenceCount()
            ->ordered()
            ->paginate($request->perPage());

        return CollectionTopicResource::collection($topics)
            ->response($request)
            ->header('Cache-Control', 'public, max-age=' . self::CACHE_MAX_AGE);
    }
}
