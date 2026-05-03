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
     * List the caller's highlights for a given segment, joining
     * `segment_contents` so highlights anchored to any block in the
     * segment surface. Un-migrated rows (no `segment_content_id`) are
     * filtered out via `migrated()`.
     */
    public function __invoke(ListSabbathSchoolHighlightsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $highlights = SabbathSchoolHighlight::query()
            ->migrated()
            ->forUser($user)
            ->forSegment($request->segmentId())
            ->orderByDesc('sabbath_school_highlights.created_at')
            ->orderByDesc('sabbath_school_highlights.id')
            ->get();

        return SabbathSchoolHighlightResource::collection($highlights);
    }
}
