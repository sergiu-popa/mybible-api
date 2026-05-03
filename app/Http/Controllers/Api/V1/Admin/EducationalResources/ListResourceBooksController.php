<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\Admin\EducationalResources\ListResourceBooksRequest;
use App\Http\Resources\EducationalResources\AdminResourceBookResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListResourceBooksController
{
    public function __invoke(ListResourceBooksRequest $request): AnonymousResourceCollection
    {
        $query = ResourceBook::query()->withCount('chapters');

        $language = $request->languageFilter();
        if ($language !== null) {
            $query->forLanguage($language);
        }

        $published = $request->publishedFilter();
        if ($published !== null) {
            $query->where('is_published', $published);
        }

        $query->orderBy('language')->orderBy('position')->orderBy('id');

        $page = (int) $request->query('page', 1);
        $perPage = 25;

        return AdminResourceBookResource::collection(
            $query->paginate($perPage, page: max(1, $page)),
        );
    }
}
