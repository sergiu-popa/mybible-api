<?php

declare(strict_types=1);

namespace App\Domain\Notes\Actions;

use App\Domain\Notes\DataTransferObjects\CreateNoteData;
use App\Domain\Notes\Models\Note;

final class CreateNoteAction
{
    public function execute(CreateNoteData $data): Note
    {
        $note = new Note;
        $note->user_id = $data->user->id;
        $note->reference = $data->canonicalReference;
        $note->book = $data->reference->book;
        $note->content = $data->content;
        $note->color = $data->color;
        $note->save();

        return $note->refresh();
    }
}
