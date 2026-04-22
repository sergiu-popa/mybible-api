<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Devotionals;

use App\Domain\Devotional\Models\Devotional;
use App\Http\Requests\Devotionals\ListDevotionalArchiveRequest;
use App\Http\Resources\Devotionals\DevotionalResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Devotionals
 */
final class ListDevotionalArchiveController
{
    /**
     * Paginated archive of devotionals for a language + type, newest first.
     * Only includes entries dated up to today. Optional `from` / `to` query
     * params narrow the window.
     */
    public function __invoke(ListDevotionalArchiveRequest $request): AnonymousResourceCollection
    {
        $data = $request->toData();

        $paginator = Devotional::query()
            ->forLanguage($data->language)
            ->ofType($data->type)
            ->publishedUpTo(CarbonImmutable::today())
            ->withinRange($data->from, $data->to)
            ->newestFirst()
            ->paginate($data->perPage);

        return DevotionalResource::collection($paginator);
    }
}
