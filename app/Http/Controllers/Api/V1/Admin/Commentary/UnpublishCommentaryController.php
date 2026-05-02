<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\SetCommentaryPublicationAction;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\UnpublishCommentaryRequest;
use App\Http\Resources\Commentary\AdminCommentaryResource;

final class UnpublishCommentaryController
{
    public function __invoke(
        UnpublishCommentaryRequest $request,
        Commentary $commentary,
        SetCommentaryPublicationAction $action,
    ): AdminCommentaryResource {
        return AdminCommentaryResource::make($action->execute($commentary, false));
    }
}
