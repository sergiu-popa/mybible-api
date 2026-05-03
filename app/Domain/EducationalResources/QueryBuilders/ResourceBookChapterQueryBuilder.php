<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\QueryBuilders;

use App\Domain\EducationalResources\Models\ResourceBookChapter;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<ResourceBookChapter>
 */
final class ResourceBookChapterQueryBuilder extends Builder
{
    public function ordered(): self
    {
        return $this->orderBy('position');
    }
}
