<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use App\Http\Requests\Hymnal\ListHymnalBookSongsRequest;
use App\Http\Resources\Hymnal\HymnalSongSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Hymnal
 */
final class ListHymnalBookSongsController
{
    /**
     * List songs in a hymnal book.
     *
     * Returns a paginated list of songs belonging to the given book, optionally
     * filtered by a `search` query. Numeric queries also match the song number.
     */
    public function __invoke(
        ListHymnalBookSongsRequest $request,
        HymnalBook $book,
    ): AnonymousResourceCollection {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        $songs = HymnalSong::query()
            ->forBook($book)
            ->search($request->search(), $language)
            ->with('book')
            ->orderBy('number')
            ->orderBy('id')
            ->paginate($request->perPage());

        return HymnalSongSummaryResource::collection($songs);
    }
}
