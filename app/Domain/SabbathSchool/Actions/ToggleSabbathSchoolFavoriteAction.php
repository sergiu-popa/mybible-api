<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolFavoriteData;
use App\Domain\SabbathSchool\DataTransferObjects\ToggleSabbathSchoolFavoriteResult;
use App\Domain\SabbathSchool\Models\SabbathSchoolFavorite;
use Illuminate\Support\Facades\DB;

final class ToggleSabbathSchoolFavoriteAction
{
    /**
     * Flip the favorite state on `(user, lesson, segment)`.
     *
     * When `segmentId` equals the whole-lesson sentinel (0), the row
     * represents a lesson-level favorite. Segment-level and lesson-level
     * favorites coexist independently on the unique index — toggling one
     * does not affect the other.
     */
    public function execute(ToggleSabbathSchoolFavoriteData $data): ToggleSabbathSchoolFavoriteResult
    {
        return DB::transaction(function () use ($data): ToggleSabbathSchoolFavoriteResult {
            /** @var SabbathSchoolFavorite|null $existing */
            $existing = SabbathSchoolFavorite::withTrashed()
                ->where('user_id', $data->user->id)
                ->where('sabbath_school_lesson_id', $data->lessonId)
                ->where('sabbath_school_segment_id', $data->segmentId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && ! $existing->trashed()) {
                $existing->delete();

                return ToggleSabbathSchoolFavoriteResult::deleted();
            }

            if ($existing !== null && $existing->trashed()) {
                $existing->restore();
                $existing->touch();

                return ToggleSabbathSchoolFavoriteResult::created($existing);
            }

            $favorite = SabbathSchoolFavorite::query()->create([
                'user_id' => $data->user->id,
                'sabbath_school_lesson_id' => $data->lessonId,
                'sabbath_school_segment_id' => $data->segmentId,
            ]);

            return ToggleSabbathSchoolFavoriteResult::created($favorite);
        });
    }
}
