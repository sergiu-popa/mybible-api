<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Commentary\ListCommentaryChapterTextsRequest;
use App\Http\Resources\Commentary\CommentaryTextResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCommentaryChapterTextsController
{
    public function __invoke(
        ListCommentaryChapterTextsRequest $request,
        Commentary $commentary,
    ): AnonymousResourceCollection {
        $texts = $commentary->texts()
            ->forBookChapter($request->book(), $request->chapter())
            ->orderBy('position')
            ->get();

        return CommentaryTextResource::collection($texts);
    }
}
