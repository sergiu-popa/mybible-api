<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;

final class SetResourceBookPublicationAction
{
    public function execute(ResourceBook $book, bool $published): ResourceBook
    {
        $book->is_published = $published;

        if ($published && $book->published_at === null) {
            $book->published_at = now();
        }

        $book->save();

        Cache::tags(ResourceBooksCacheKeys::tagsForBook($book->id))->flush();
        Cache::tags(ResourceBooksCacheKeys::tagsForList())->flush();

        return $book;
    }
}
