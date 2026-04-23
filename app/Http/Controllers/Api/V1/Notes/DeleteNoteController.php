<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notes;

use App\Domain\Notes\Actions\DeleteNoteAction;
use App\Domain\Notes\Models\Note;
use App\Http\Requests\Notes\DeleteNoteRequest;
use Illuminate\Http\Response;

/**
 * @tags Notes
 */
final class DeleteNoteController
{
    /**
     * Delete a note.
     *
     * Permanently deletes the authenticated user's note. Returns 204 on
     * success.
     */
    public function __invoke(
        DeleteNoteRequest $request,
        Note $note,
        DeleteNoteAction $action,
    ): Response {
        $action->execute($note);

        return response()->noContent();
    }
}
