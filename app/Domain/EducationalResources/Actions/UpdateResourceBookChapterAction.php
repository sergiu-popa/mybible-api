<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;

final class UpdateResourceBookChapterAction
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function execute(ResourceBookChapter $chapter, array $changes): ResourceBookChapter
    {
        $chapter->fill($changes);
        $chapter->save();

        Cache::tags(ResourceBooksCacheKeys::tagsForBook($chapter->resource_book_id))->flush();

        return $chapter;
    }
}
