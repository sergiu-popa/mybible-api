<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Http\Requests\Hymnal\ListHymnalBooksRequest;
use App\Http\Resources\Hymnal\HymnalBookResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Hymnal
 */
final class ListHymnalBooksController
{
    /**
     * List hymnal books.
     *
     * Returns a paginated list of hymnal books, optionally filtered by the
     * `language` query parameter. Each row carries a `song_count` aggregate.
     */
    public function __invoke(ListHymnalBooksRequest $request): AnonymousResourceCollection
    {
        $query = HymnalBook::query()
            ->withSongCount()
            ->orderBy('position')
            ->orderBy('id');

        $language = $request->languageFilter();
        if ($language !== null) {
            $query->forLanguage($language);
        }

        return HymnalBookResource::collection($query->paginate($request->perPage()));
    }
}
