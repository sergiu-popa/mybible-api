<?php

declare(strict_types=1);

namespace App\Domain\Hymnal\QueryBuilders;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<HymnalBook>
 */
final class HymnalBookQueryBuilder extends Builder
{
    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function withSongCount(): self
    {
        return $this->withCount('songs');
    }
}
