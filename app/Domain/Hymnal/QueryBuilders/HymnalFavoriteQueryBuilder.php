<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\QueryBuilders;

use App\Domain\Hymnal\Models\HymnalFavorite;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<HymnalFavorite>
 */
final class HymnalFavoriteQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    public function forSong(HymnalSong $song): self
    {
        return $this->where('hymnal_song_id', $song->id);
    }

    public function withSong(): self
    {
        return $this->with(['song.book']);
    }
}
