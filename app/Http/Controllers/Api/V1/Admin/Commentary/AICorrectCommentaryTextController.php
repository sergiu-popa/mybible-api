<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\CorrectCommentaryTextAction;
use App\Domain\Commentary\DataTransferObjects\AICorrectCommentaryTextData;
use App\Domain\Commentary\Models\CommentaryText;
use App\Http\Requests\Admin\Commentary\AICorrectCommentaryTextRequest;
use App\Http\Resources\Commentary\AdminCommentaryTextResource;
use App\Support\Controllers\ResolvesTriggeringUser;

final class AICorrectCommentaryTextController
{
    use ResolvesTriggeringUser;

    public function __invoke(
        AICorrectCommentaryTextRequest $request,
        CommentaryText $text,
        CorrectCommentaryTextAction $action,
    ): AdminCommentaryTextResource {
        $userId = $this->triggeringUserId($request);

        $text->loadMissing('commentary');

        $updated = $action->execute(new AICorrectCommentaryTextData(
            text: $text,
            triggeredByUserId: $userId,
        ));

        return AdminCommentaryTextResource::make($updated);
    }
}
