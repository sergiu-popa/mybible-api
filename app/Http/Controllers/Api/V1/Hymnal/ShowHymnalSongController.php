<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Hymnal;

use App\Domain\Hymnal\Models\HymnalSong;
use App\Http\Requests\Hymnal\ShowHymnalSongRequest;
use App\Http\Resources\Hymnal\HymnalSongResource;

/**
 * @tags Hymnal
 */
final class ShowHymnalSongController
{
    /**
     * Show a hymnal song.
     *
     * Returns the full song payload including localised metadata and stanzas.
     */
    public function __invoke(ShowHymnalSongRequest $request, HymnalSong $song): HymnalSongResource
    {
        $song->load('book');

        return HymnalSongResource::make($song);
    }
}
