<?php

declare(strict_types=1);

namespace App\Http\Requests\Notes;

use App\Domain\Notes\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

abstract class AuthorizedNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');
        $user = $this->user();

        if (! $note instanceof Note || ! $user instanceof User) {
            return false;
        }

        return $user->can('manage', $note);
    }
}
