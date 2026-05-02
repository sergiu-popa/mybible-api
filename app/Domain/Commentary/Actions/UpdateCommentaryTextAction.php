<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\Models\CommentaryText;

final class UpdateCommentaryTextAction
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function execute(CommentaryText $text, array $changes): CommentaryText
    {
        $text->fill($changes)->save();

        return $text;
    }
}
