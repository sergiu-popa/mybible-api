<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\QueryBuilders;

use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<ResourceCategory>
 */
final class ResourceCategoryQueryBuilder extends Builder
{
    public function withResourceCount(): self
    {
        return $this->withCount('resources as resource_count');
    }

    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }
}
