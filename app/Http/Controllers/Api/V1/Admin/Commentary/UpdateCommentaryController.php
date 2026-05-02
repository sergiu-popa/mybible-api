<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\UpdateCommentaryAction;
use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\UpdateCommentaryRequest;
use App\Http\Resources\Commentary\AdminCommentaryResource;

final class UpdateCommentaryController
{
    public function __invoke(
        UpdateCommentaryRequest $request,
        Commentary $commentary,
        UpdateCommentaryAction $action,
    ): AdminCommentaryResource {
        return AdminCommentaryResource::make($action->execute($commentary, $request->changes()));
    }
}
