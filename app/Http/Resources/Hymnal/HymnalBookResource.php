<?php

declare(strict_types=1);

namespace App\Http\Resources\Hymnal;

use App\Domain\Hymnal\Models\HymnalBook;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HymnalBook
 */
final class HymnalBookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => LanguageResolver::resolve($this->name, $language),
            'language' => $this->language,
            'song_count' => $this->songs_count,
        ];
    }
}
