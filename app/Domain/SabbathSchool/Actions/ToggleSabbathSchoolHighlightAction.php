<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolHighlightData;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolHighlightResult;
use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use Illuminate\Support\Facades\DB;

final class ToggleSabbathSchoolHighlightAction
{
    /**
     * Flip the highlight state for `(user, segment_content, range)`.
     *
     * Identical (user, content, start, end) deletes; otherwise creates.
     * Color updates do not flow through this action — see
     * {@see PatchSabbathSchoolHighlightColorAction}.
     */
    public function execute(ToggleSabbathSchoolHighlightData $data): ToggleSabbathSchoolHighlightResult
    {
        return DB::transaction(function () use ($data): ToggleSabbathSchoolHighlightResult {
            $existing = SabbathSchoolHighlight::query()
                ->forUser($data->user)
                ->forContentRange($data->segmentContentId, $data->startPosition, $data->endPosition)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();

                return ToggleSabbathSchoolHighlightResult::deleted();
            }

            $segmentId = SabbathSchoolSegmentContent::query()
                ->whereKey($data->segmentContentId)
                ->value('segment_id');

            $highlight = SabbathSchoolHighlight::query()->create([
                'user_id' => $data->user->id,
                'sabbath_school_segment_id' => $segmentId,
                'segment_content_id' => $data->segmentContentId,
                'start_position' => $data->startPosition,
                'end_position' => $data->endPosition,
                'color' => $data->color,
                'passage' => null,
            ]);

            return ToggleSabbathSchoolHighlightResult::created($highlight);
        });
    }
}
