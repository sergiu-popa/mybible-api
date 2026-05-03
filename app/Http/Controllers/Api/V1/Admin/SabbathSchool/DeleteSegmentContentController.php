<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\SabbathSchool;

use App\Domain\SabbathSchool\Actions\DeleteSegmentContentAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolSegmentContent;
use App\Http\Requests\Admin\SabbathSchool\DeleteSegmentContentRequest;
use Illuminate\Http\Response;

final class DeleteSegmentContentController
{
    public function __invoke(
        DeleteSegmentContentRequest $request,
        SabbathSchoolSegmentContent $content,
        DeleteSegmentContentAction $action,
    ): Response {
        $action->execute($content);

        return response()->noContent();
    }
}
