<?php

declare(strict_types=1);

namespace App\Domain\Devotional\QueryBuilders;

use App\Domain\Devotional\Models\DevotionalFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<DevotionalFavorite>
 */
final class DevotionalFavoriteQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    public function withDevotional(): self
    {
        return $this->with('devotional');
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('created_at')->orderByDesc('id');
    }
}
