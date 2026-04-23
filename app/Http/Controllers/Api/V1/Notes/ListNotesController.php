<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notes;

use App\Domain\Notes\Models\Note;
use App\Http\Requests\Notes\ListNotesRequest;
use App\Http\Resources\Notes\NoteResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Notes
 */
final class ListNotesController
{
    /**
     * List notes.
     *
     * Returns the authenticated user's notes, newest first. Optionally
     * filter by a 3-letter Bible book abbreviation via `?book=GEN`.
     */
    public function __invoke(ListNotesRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $notes = Note::query()
            ->forUser($user)
            ->forBook($request->book())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return NoteResource::collection($notes);
    }
}
