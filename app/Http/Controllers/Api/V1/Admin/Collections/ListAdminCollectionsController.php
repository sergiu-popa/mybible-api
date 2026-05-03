<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Models\Collection;
use App\Http\Requests\Admin\Collections\ListAdminCollectionsRequest;
use App\Http\Resources\Collections\CollectionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminCollectionsController
{
    public function __invoke(ListAdminCollectionsRequest $request): AnonymousResourceCollection
    {
        $query = Collection::query()->withTopicsCount()->ordered();

        $language = $request->language();
        if ($language !== null) {
            $query->where('language', $language);
        }

        return CollectionResource::collection(
            $query->paginate($request->perPage(), page: $request->pageNumber()),
        );
    }
}
