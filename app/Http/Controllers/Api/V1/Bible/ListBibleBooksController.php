<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bible;

use App\Domain\Bible\Models\BibleBook;
use App\Http\Requests\Bible\ListBibleBooksRequest;
use App\Http\Resources\Bible\BibleBookResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Bible
 */
final class ListBibleBooksController
{
    public function __invoke(ListBibleBooksRequest $request): AnonymousResourceCollection
    {
        $books = BibleBook::query()->inCanonicalOrder()->get();

        return BibleBookResource::collection($books);
    }
}
