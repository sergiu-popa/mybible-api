<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Http\Requests\Admin\SabbathSchool\ListAdminLessonsRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolLessonSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminLessonsController
{
    public function __invoke(ListAdminLessonsRequest $request): AnonymousResourceCollection
    {
        $query = SabbathSchoolLesson::query()
            ->orderByDesc('date_from')
            ->orderByDesc('id');

        $language = $request->language();
        if ($language !== null) {
            $query->forLanguage($language);
        }

        $trimesterId = $request->trimesterId();
        if ($trimesterId !== null) {
            $query->forTrimester($trimesterId);
        }

        $ageGroup = $request->ageGroup();
        if ($ageGroup !== null) {
            $query->forAgeGroup($ageGroup);
        }

        $published = $request->published();
        if ($published === true) {
            $query->published();
        } elseif ($published === false) {
            $query->whereNull('published_at');
        }

        return SabbathSchoolLessonSummaryResource::collection(
            $query->paginate($request->perPage()),
        );
    }
}
