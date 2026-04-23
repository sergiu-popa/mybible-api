<?php

declare(strict_types=1);

namespace App\Domain\Notes\Actions;

use App\Domain\Notes\DataTransferObjects\UpdateNoteData;
use App\Domain\Notes\Models\Note;

final class UpdateNoteAction
{
    public function execute(UpdateNoteData $data): Note
    {
        $note = $data->note;
        $note->content = $data->content;
        $note->save();

        return $note->refresh();
    }
}
