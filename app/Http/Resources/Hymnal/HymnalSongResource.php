<?php

declare(strict_types=1);

namespace App\Http\Resources\Hymnal;

use App\Domain\Hymnal\Models\HymnalSong;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HymnalSong
 */
final class HymnalSongResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => LanguageResolver::resolve($this->title, $language),
            'author' => $this->author !== null
                ? LanguageResolver::resolve($this->author, $language)
                : null,
            'composer' => $this->composer !== null
                ? LanguageResolver::resolve($this->composer, $language)
                : null,
            'copyright' => $this->copyright !== null
                ? LanguageResolver::resolve($this->copyright, $language)
                : null,
            'stanzas' => $this->resolveStanzas($language),
            'book' => [
                'id' => $this->hymnal_book_id,
                'slug' => $this->whenLoaded('book', fn () => $this->book->slug),
                'name' => $this->whenLoaded(
                    'book',
                    fn () => LanguageResolver::resolve($this->book->name, $language),
                ),
            ],
        ];
    }

    /**
     * @return list<array{index: int, text: string, is_chorus: bool}>
     */
    private function resolveStanzas(Language $language): array
    {
        /** @var mixed $stanzas */
        $stanzas = $this->stanzas;

        if (! is_array($stanzas)) {
            return [];
        }

        /** @var mixed $locale */
        $locale = $stanzas[$language->value] ?? $stanzas[Language::En->value] ?? null;

        if (! is_array($locale)) {
            return [];
        }

        $result = [];
        foreach ($locale as $stanza) {
            if (! is_array($stanza)) {
                continue;
            }

            $result[] = [
                'index' => (int) ($stanza['index'] ?? 0),
                'text' => (string) ($stanza['text'] ?? ''),
                'is_chorus' => (bool) ($stanza['is_chorus'] ?? false),
            ];
        }

        return $result;
    }
}
