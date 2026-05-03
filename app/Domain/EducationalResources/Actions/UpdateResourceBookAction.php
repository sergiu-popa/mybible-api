<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use Illuminate\Support\Facades\Cache;

final class UpdateResourceBookAction
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function execute(ResourceBook $book, array $changes): ResourceBook
    {
        $book->fill($changes);
        $book->save();

        Cache::tags(ResourceBooksCacheKeys::tagsForBook($book->id))->flush();
        Cache::tags(ResourceBooksCacheKeys::tagsForList())->flush();

        return $book;
    }
}
