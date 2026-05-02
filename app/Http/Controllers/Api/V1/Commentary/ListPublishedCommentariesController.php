<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Commentary\ListPublishedCommentariesRequest;
use App\Http\Resources\Commentary\CommentaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListPublishedCommentariesController
{
    public function __invoke(ListPublishedCommentariesRequest $request): AnonymousResourceCollection
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        $commentaries = Commentary::query()
            ->with('sourceCommentary')
            ->published()
            ->forLanguage($language)
            ->orderBy('abbreviation')
            ->get();

        return CommentaryResource::collection($commentaries);
    }
}
