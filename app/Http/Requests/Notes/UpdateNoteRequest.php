<?php

declare(strict_types=1);

namespace App\Http\Requests\Notes;

use App\Domain\Notes\DataTransferObjects\UpdateNoteData;
use App\Domain\Notes\Models\Note;
use App\Http\Rules\HexColor;
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
            'color' => ['sometimes', 'nullable', 'string', new HexColor],
        ];
    }

    public function toData(Note $note): UpdateNoteData
    {
        /** @var string $content */
        $content = $this->validated('content');

        $colorProvided = $this->has('color');
        $color = null;
        if ($colorProvided) {
            $colorRaw = $this->input('color');
            $color = is_string($colorRaw) && $colorRaw !== '' ? $colorRaw : null;
        }

        return new UpdateNoteData(
            note: $note,
            content: $content,
            color: $color,
            colorProvided: $colorProvided,
        );
    }
}
