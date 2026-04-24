<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Http\Requests\SabbathSchool\ListSabbathSchoolHighlightsRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolHighlightResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Sabbath School
 */
final class ListSabbathSchoolHighlightsController
{
    /**
     * List the caller's highlights for a given segment.
     *
     * Returns all rows belonging to the authenticated user on the requested
     * segment. Ordered newest-first.
     */
    public function __invoke(ListSabbathSchoolHighlightsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $highlights = SabbathSchoolHighlight::query()
            ->forUser($user)
            ->forSegment($request->segmentId())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return SabbathSchoolHighlightResource::collection($highlights);
    }
}
