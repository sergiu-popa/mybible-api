<?php

declare(strict_types=1);

namespace App\Domain\Notes\QueryBuilders;

use App\Domain\Notes\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Note>
 */
final class NoteQueryBuilder extends Builder
{
    public function forUser(User $user): self
    {
        return $this->where('user_id', $user->id);
    }

    public function forBook(?string $book): self
    {
        if ($book === null || $book === '') {
            return $this;
        }

        return $this->where('book', strtoupper($book));
    }
}
