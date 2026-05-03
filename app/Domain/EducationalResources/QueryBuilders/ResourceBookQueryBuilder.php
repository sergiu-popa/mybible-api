<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\QueryBuilders;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<ResourceBook>
 */
final class ResourceBookQueryBuilder extends Builder
{
    public function published(): self
    {
        return $this->where('is_published', true);
    }

    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function orderedForList(): self
    {
        return $this->orderBy('position')->orderByDesc('published_at');
    }
}
