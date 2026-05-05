<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Actions\AddReferencesCommentaryTextAction;
use App\Domain\Commentary\DataTransferObjects\AIAddReferencesCommentaryTextData;
use App\Domain\Commentary\Models\CommentaryText;
use App\Http\Requests\Admin\Commentary\AIAddReferencesCommentaryTextRequest;
use App\Http\Resources\Commentary\AdminCommentaryTextResource;
use App\Support\Controllers\ResolvesTriggeringUser;

final class AIAddReferencesCommentaryTextController
{
    use ResolvesTriggeringUser;

    public function __invoke(
        AIAddReferencesCommentaryTextRequest $request,
        CommentaryText $text,
        AddReferencesCommentaryTextAction $action,
    ): AdminCommentaryTextResource {
        $userId = $this->triggeringUserId($request);

        $text->loadMissing('commentary');

        $updated = $action->execute(new AIAddReferencesCommentaryTextData(
            text: $text,
            triggeredByUserId: $userId,
        ));

        return AdminCommentaryTextResource::make($updated);
    }
}
