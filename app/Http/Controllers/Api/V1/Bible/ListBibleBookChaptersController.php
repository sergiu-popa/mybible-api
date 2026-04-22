<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bible;

use App\Domain\Bible\Models\BibleBook;
use App\Http\Requests\Bible\ListBibleBookChaptersRequest;
use App\Http\Resources\Bible\BibleChapterResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Bible
 */
final class ListBibleBookChaptersController
{
    public function __invoke(ListBibleBookChaptersRequest $request, BibleBook $book): AnonymousResourceCollection
    {
        $chapters = $book->chapters()->orderBy('number')->get();

        return BibleChapterResource::collection($chapters);
    }
}
