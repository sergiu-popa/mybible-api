<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Notes\Models\Note;
use App\Models\User;

final class NotePolicy
{
    public function manage(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }
}
