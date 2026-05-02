<?php

declare(strict_types=1);

namespace App\Http\Resources\Favorites;

use App\Domain\Favorites\Models\Favorite;
use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Favorite
 */
final class FavoriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reference = $this->parseReference();
        $language = $this->resolveLanguage($request);

        $base = [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'reference' => $this->reference,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];

        if ($reference === null) {
            return $base + [
                'book' => null,
                'chapter' => null,
                'verses' => [],
                'version' => null,
                'human_readable' => null,
            ];
        }

        $formatter = app(ReferenceFormatter::class);

        return $base + [
            'book' => $reference->book,
            'chapter' => $reference->chapter,
            'verses' => $reference->verses,
            'version' => $reference->version,
            'human_readable' => $formatter->toHumanReadable($reference, $language->value),
        ];
    }

    private function parseReference(): ?Reference
    {
        if ($this->reference === '') {
            return null;
        }

        try {
            $references = app(ReferenceParser::class)->parse($this->reference);
        } catch (InvalidReferenceException) {
            return null;
        }

        $first = $references[0] ?? null;

        return $first instanceof Reference ? $first : null;
    }

    private function resolveLanguage(Request $request): Language
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return $language instanceof Language ? $language : Language::En;
    }
}
