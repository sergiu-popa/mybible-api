<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\DeleteCommentaryTextAction;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use App\Http\Requests\Admin\Commentary\DeleteCommentaryTextRequest;
use Illuminate\Http\Response;

final class DeleteCommentaryTextController
{
    public function __invoke(
        DeleteCommentaryTextRequest $request,
        Commentary $commentary,
        CommentaryText $text,
        DeleteCommentaryTextAction $action,
    ): Response {
        $action->execute($text);

        return response()->noContent();
    }
}
