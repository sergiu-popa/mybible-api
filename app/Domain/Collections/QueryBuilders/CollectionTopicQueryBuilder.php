<?php

declare(strict_types=1);

namespace App\Domain\Collections\QueryBuilders;

use App\Domain\Collections\Models\CollectionTopic;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<CollectionTopic>
 */
final class CollectionTopicQueryBuilder extends Builder
{
    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function withReferenceCount(): self
    {
        return $this->withCount('references as reference_count');
    }

    public function ordered(): self
    {
        return $this->orderBy('position')->orderBy('id');
    }
}
