<?php

declare(strict_types=1);

namespace App\Http\Requests\Notes;

use App\Domain\Notes\DataTransferObjects\CreateNoteData;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use App\Http\Rules\HexColor;
use App\Http\Rules\StripHtml;
use App\Http\Rules\ValidReference;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class StoreNoteRequest extends FormRequest
{
    public const CONTENT_MAX_LENGTH = 10_000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                new ValidReference($this->container->make(ReferenceParser::class), $this),
            ],
            'content' => [
                'required',
                'string',
                new StripHtml,
                'max:' . self::CONTENT_MAX_LENGTH,
            ],
            'color' => ['nullable', 'string', new HexColor],
        ];
    }

    public function toData(ReferenceFormatter $formatter): CreateNoteData
    {
        /** @var User $user */
        $user = $this->user();

        $reference = $this->attributes->get(ValidReference::PARSED_ATTRIBUTE_KEY);

        if (! $reference instanceof Reference) {
            // Should never happen — ValidReference populates this on success.
            throw new RuntimeException('Parsed reference missing from request attributes.');
        }

        /** @var string $content */
        $content = $this->validated('content');

        $color = $this->validated('color');

        return new CreateNoteData(
            user: $user,
            reference: $reference,
            canonicalReference: $formatter->toCanonical($reference),
            content: $content,
            color: is_string($color) && $color !== '' ? $color : null,
        );
    }
}
