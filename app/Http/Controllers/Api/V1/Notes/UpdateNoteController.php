<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notes;

use App\Domain\Notes\Actions\UpdateNoteAction;
use App\Domain\Notes\Models\Note;
use App\Http\Requests\Notes\UpdateNoteRequest;
use App\Http\Resources\Notes\NoteResource;

/**
 * @tags Notes
 */
final class UpdateNoteController
{
    /**
     * Update a note.
     *
     * Updates the authenticated user's note content. The `reference`
     * attribute is immutable — any `reference` field in the body is
     * ignored.
     */
    public function __invoke(
        UpdateNoteRequest $request,
        Note $note,
        UpdateNoteAction $action,
    ): NoteResource {
        return NoteResource::make($action->execute($request->toData($note)));
    }
}
