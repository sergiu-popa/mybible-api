<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\UpdateTrimesterAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Http\Requests\Admin\SabbathSchool\UpdateTrimesterRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolTrimesterResource;

final class UpdateTrimesterController
{
    public function __invoke(
        UpdateTrimesterRequest $request,
        SabbathSchoolTrimester $trimester,
        UpdateTrimesterAction $action,
    ): SabbathSchoolTrimesterResource {
        return SabbathSchoolTrimesterResource::make(
            $action->execute($trimester, $request->toData()),
        );
    }
}
