<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Devotionals;

use App\Domain\Devotional\Models\DevotionalType;
use App\Http\Requests\Admin\Devotionals\ListDevotionalTypesRequest;
use App\Http\Resources\Devotionals\DevotionalTypeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListDevotionalTypesController
{
    public function __invoke(ListDevotionalTypesRequest $request): AnonymousResourceCollection
    {
        $query = DevotionalType::query()->ordered();

        $language = $request->query('language');

        if (is_string($language) && $language !== '') {
            $query->where(function ($q) use ($language): void {
                $q->where('language', $language)->orWhereNull('language');
            });
        }

        return DevotionalTypeResource::collection($query->paginate($request->perPage()));
    }
}
