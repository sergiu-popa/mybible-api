<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\SetCommentaryPublicationAction;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\PublishCommentaryRequest;
use App\Http\Resources\Commentary\AdminCommentaryResource;

final class PublishCommentaryController
{
    public function __invoke(
        PublishCommentaryRequest $request,
        Commentary $commentary,
        SetCommentaryPublicationAction $action,
    ): AdminCommentaryResource {
        return AdminCommentaryResource::make($action->execute($commentary, true));
    }
}
