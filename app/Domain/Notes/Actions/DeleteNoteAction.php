<?php

declare(strict_types=1);

namespace App\Domain\Notes\Actions;

use App\Domain\Notes\Models\Note;

final class DeleteNoteAction
{
    public function execute(Note $note): void
    {
        $note->delete();
    }
}
