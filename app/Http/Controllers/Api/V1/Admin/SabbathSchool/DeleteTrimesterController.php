<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\DeleteTrimesterAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Http\Requests\Admin\SabbathSchool\DeleteTrimesterRequest;
use Illuminate\Http\Response;

final class DeleteTrimesterController
{
    public function __invoke(
        DeleteTrimesterRequest $request,
        SabbathSchoolTrimester $trimester,
        DeleteTrimesterAction $action,
    ): Response {
        $action->execute($trimester);

        return response()->noContent();
    }
}
