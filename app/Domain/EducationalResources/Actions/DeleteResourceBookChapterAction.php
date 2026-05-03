<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;

final class DeleteResourceBookChapterAction
{
    public function execute(ResourceBookChapter $chapter): void
    {
        $bookId = $chapter->resource_book_id;
        $chapter->delete();

        Cache::tags(ResourceBooksCacheKeys::tagsForBook($bookId))->flush();
    }
}
