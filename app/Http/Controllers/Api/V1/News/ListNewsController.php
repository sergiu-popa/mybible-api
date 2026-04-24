<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\News;

use App\Domain\News\Models\News;
use App\Http\Requests\News\ListNewsRequest;
use App\Http\Resources\News\NewsResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags News
 */
final class ListNewsController
{
    /**
     * List news articles.
     *
     * Returns a paginated list of published news for the resolved language,
     * newest first. `language` query parameter overrides the resolved
     * request language; defaults apply when absent.
     */
    public function __invoke(ListNewsRequest $request): AnonymousResourceCollection
    {
        $query = News::query()
            ->published()
            ->forLanguage($request->resolvedLanguage())
            ->newestFirst();

        return NewsResource::collection($query->paginate($request->perPage()));
    }
}
