<?php

declare(strict_types=1);

namespace App\Http\Requests\Notes;

use App\Domain\Notes\DataTransferObjects\UpdateNoteData;
use App\Domain\Notes\Models\Note;
use App\Http\Rules\StripHtml;

final class UpdateNoteRequest extends AuthorizedNoteRequest
{
    public const CONTENT_MAX_LENGTH = 10_000;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => [
                'required',
                'string',
                new StripHtml,
                'max:' . self::CONTENT_MAX_LENGTH,
            ],
        ];
    }

    public function toData(Note $note): UpdateNoteData
    {
        /** @var string $content */
        $content = $this->validated('content');

        return new UpdateNoteData(
            note: $note,
            content: $content,
        );
    }
}
