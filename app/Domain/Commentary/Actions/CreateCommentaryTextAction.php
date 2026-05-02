<?php

declare(strict_types=1);

namespace App\Domain\Commentary\Actions;

use App\Domain\Commentary\DataTransferObjects\CommentaryTextData;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;

final class CreateCommentaryTextAction
{
    public function execute(Commentary $commentary, CommentaryTextData $data): CommentaryText
    {
        return $commentary->texts()->create([
            'book' => $data->book,
            'chapter' => $data->chapter,
            'position' => $data->position,
            'verse_from' => $data->verseFrom,
            'verse_to' => $data->verseTo,
            'verse_label' => $data->verseLabel,
            'content' => $data->content,
        ]);
    }
}
