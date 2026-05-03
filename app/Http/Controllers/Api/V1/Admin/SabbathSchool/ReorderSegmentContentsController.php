<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\ReorderSegmentContentsAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegment;
use App\Http\Requests\Admin\ReorderRequest;
use Illuminate\Http\JsonResponse;

final class ReorderSegmentContentsController
{
    public function __invoke(
        ReorderRequest $request,
        SabbathSchoolSegment $segment,
        ReorderSegmentContentsAction $action,
    ): JsonResponse {
        $action->execute($segment, $request->ids());

        return response()->json(['message' => 'Reordered.']);
    }
}
