<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\ListCommentariesRequest;
use App\Http\Resources\Commentary\AdminCommentaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCommentariesController
{
    public function __invoke(ListCommentariesRequest $request): AnonymousResourceCollection
    {
        $query = Commentary::query();

        $language = $request->languageFilter();
        if ($language !== null) {
            $query->forLanguage($language);
        } else {
            $query->orderBy('language');
        }

        $query->orderBy('abbreviation');

        $published = $request->publishedFilter();
        if ($published !== null) {
            $query->where('is_published', $published);
        }

        return AdminCommentaryResource::collection($query->get());
    }
}
