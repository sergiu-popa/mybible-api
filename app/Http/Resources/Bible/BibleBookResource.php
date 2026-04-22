<?php

declare(strict_types=1);

namespace App\Http\Resources\Bible;

use App\Domain\Bible\Models\BibleBook;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BibleBook
 */
final class BibleBookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return [
            'id' => $this->id,
            'abbreviation' => $this->abbreviation,
            'name' => LanguageResolver::resolve($this->names, $language),
            'testament' => $this->testament,
            'position' => $this->position,
            'chapter_count' => $this->chapter_count,
        ];
    }
}
