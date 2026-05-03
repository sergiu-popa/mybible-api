<?php

declare(strict_types=1);

namespace App\Domain\Collections\QueryBuilders;

use App\Domain\Collections\Models\Collection;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Collection>
 */
final class CollectionQueryBuilder extends Builder
{
    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function ordered(): self
    {
        return $this->orderBy('position')->orderBy('id');
    }

    public function withTopicsCount(): self
    {
        return $this->withCount('topics');
    }
}
