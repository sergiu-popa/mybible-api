<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\UpdateCommentaryTextAction;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use App\Http\Requests\Admin\Commentary\UpdateCommentaryTextRequest;
use App\Http\Resources\Commentary\AdminCommentaryTextResource;

final class UpdateCommentaryTextController
{
    public function __invoke(
        UpdateCommentaryTextRequest $request,
        Commentary $commentary,
        CommentaryText $text,
        UpdateCommentaryTextAction $action,
    ): AdminCommentaryTextResource {
        return AdminCommentaryTextResource::make($action->execute($text, $request->changes()));
    }
}
