<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Commentary\ListCommentaryVerseTextsRequest;
use App\Http\Resources\Commentary\CommentaryTextResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCommentaryVerseTextsController
{
    public function __invoke(
        ListCommentaryVerseTextsRequest $request,
        Commentary $commentary,
    ): AnonymousResourceCollection {
        $texts = $commentary->texts()
            ->coveringVerse($request->book(), $request->chapter(), $request->verse())
            ->orderBy('position')
            ->get();

        return CommentaryTextResource::collection($texts);
    }
}
