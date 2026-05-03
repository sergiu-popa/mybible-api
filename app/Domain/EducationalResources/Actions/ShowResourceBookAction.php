<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Actions;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Support\ResourceBooksCacheKeys;
use App\Http\Resources\EducationalResources\ResourceBookDetailResource;
use App\Support\Caching\CachedRead;

final class ShowResourceBookAction
{
    public function __construct(private readonly CachedRead $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(ResourceBook $book): array
    {
        return $this->cache->read(
            ResourceBooksCacheKeys::detail($book->slug),
            ResourceBooksCacheKeys::tagsForBook($book->id),
            3600,
            static function () use ($book): array {
                $book->load(['chapters' => static function ($query): void {
                    $query->orderBy('position');
                }]);

                return ResourceBookDetailResource::make($book)
                    ->response(request())
                    ->getData(true);
            },
        );
    }
}
