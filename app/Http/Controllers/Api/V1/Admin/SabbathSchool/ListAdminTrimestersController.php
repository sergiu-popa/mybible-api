<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Http\Requests\Admin\SabbathSchool\ListAdminTrimestersRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolTrimesterResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminTrimestersController
{
    public function __invoke(ListAdminTrimestersRequest $request): AnonymousResourceCollection
    {
        $query = SabbathSchoolTrimester::query()
            ->orderByDesc('year')
            ->orderByDesc('number');

        $language = $request->language();
        if ($language !== null) {
            $query->forLanguage($language);
        }

        return SabbathSchoolTrimesterResource::collection($query->get());
    }
}
