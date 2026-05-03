<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;

final class DeleteResourceBookAction
{
    public function execute(ResourceBook $book): void
    {
        $book->delete();

        Cache::tags(ResourceBooksCacheKeys::tagsForBook($book->id))->flush();
        Cache::tags(ResourceBooksCacheKeys::tagsForList())->flush();
    }
}
