<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\Shared\Enums\Language;
use App\Http\Resources\Collections\CollectionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCollectionsAction
{
    public function handle(Language $language, int $page, int $perPage): AnonymousResourceCollection
    {
        $paginator = Collection::query()
            ->forLanguage($language)
            ->withTopicsCount()
            ->ordered()
            ->paginate($perPage, page: $page);

        return CollectionResource::collection($paginator);
    }
}
