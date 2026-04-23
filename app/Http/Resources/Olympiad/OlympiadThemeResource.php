<?php

declare(strict_types=1);

namespace App\Http\Resources\Olympiad;

use App\Domain\Shared\Enums\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OlympiadThemeResource extends JsonResource
{
    /**
     * The underlying `$this->resource` is a raw projection row produced by
     * `OlympiadQuestionQueryBuilder::themes()` — an `OlympiadQuestion`
     * model instance with only the selected aggregate columns populated.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $book = (string) $this->resource->getAttribute('book');
        $from = (int) $this->resource->getAttribute('chapters_from');
        $to = (int) $this->resource->getAttribute('chapters_to');
        $rawLanguage = $this->resource->getAttribute('language');
        $language = $rawLanguage instanceof Language ? $rawLanguage->value : (string) $rawLanguage;

        return [
            'id' => sprintf('%s.%d-%d.%s', $book, $from, $to, $language),
            'book' => $book,
            'chapters_from' => $from,
            'chapters_to' => $to,
            'language' => $language,
            'question_count' => (int) $this->resource->getAttribute('question_count'),
        ];
    }
}
