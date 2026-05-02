<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\Models\CommentaryText;

final class DeleteCommentaryTextAction
{
    public function execute(CommentaryText $text): void
    {
        $text->delete();
    }
}
