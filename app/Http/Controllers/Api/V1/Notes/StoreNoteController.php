<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notes;

use App\Domain\Notes\Actions\CreateNoteAction;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Http\Requests\Notes\StoreNoteRequest;
use App\Http\Resources\Notes\NoteResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @tags Notes
 */
final class StoreNoteController
{
    /**
     * Create a note.
     *
     * Attaches a private note to a specific passage for the authenticated
     * user. `reference` is validated via the canonical reference parser;
     * `content` is plain text (HTML is stripped before storage).
     */
    public function __invoke(
        StoreNoteRequest $request,
        ReferenceFormatter $formatter,
        CreateNoteAction $action,
    ): JsonResponse {
        $note = $action->execute($request->toData($formatter));

        return NoteResource::make($note)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
