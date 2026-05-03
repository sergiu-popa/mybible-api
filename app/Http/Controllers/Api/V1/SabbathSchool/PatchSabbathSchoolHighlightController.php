<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SabbathSchool;

use App\Domain\SabbathSchool\Actions\PatchSabbathSchoolHighlightColorAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;
use App\Http\Requests\SabbathSchool\PatchSabbathSchoolHighlightRequest;
use App\Http\Resources\SabbathSchool\SabbathSchoolHighlightResource;

/**
 * @tags Sabbath School
 */
final class PatchSabbathSchoolHighlightController
{
    /**
     * Update the colour of a highlight owned by the caller.
     *
     * The route-model binding scopes by `user_id`, so a cross-user
     * call surfaces 404 (the row is invisible to anyone else).
     */
    public function __invoke(
        PatchSabbathSchoolHighlightRequest $request,
        SabbathSchoolHighlight $highlight,
        PatchSabbathSchoolHighlightColorAction $action,
    ): SabbathSchoolHighlightResource {
        $updated = $action->execute($highlight, $request->color());

        return SabbathSchoolHighlightResource::make($updated);
    }
}
