<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\QueryBuilders;

use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\Models\EducationalResource;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<EducationalResource>
 */
final class EducationalResourceQueryBuilder extends Builder
{
    public function ofType(ResourceType $type): self
    {
        return $this->where('type', $type->value);
    }

    public function latestPublished(): self
    {
        return $this->orderByDesc('published_at');
    }
}
