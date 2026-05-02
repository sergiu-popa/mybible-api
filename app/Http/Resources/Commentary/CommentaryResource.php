<?php

declare(strict_types=1);

namespace App\Http\Resources\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Commentary
 */
class CommentaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        return [
            'slug' => $this->slug,
            'name' => LanguageResolver::resolve($this->name, $language),
            'abbreviation' => $this->abbreviation,
            'language' => $this->language,
            'source_commentary' => $this->whenLoaded(
                'sourceCommentary',
                fn () => $this->sourceCommentary !== null
                    ? new self($this->sourceCommentary)
                    : null,
            ),
        ];
    }
}
