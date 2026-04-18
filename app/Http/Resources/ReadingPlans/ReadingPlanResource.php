<?php

declare(strict_types=1);

namespace App\Http\Resources\ReadingPlans;

use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReadingPlan
 */
final class ReadingPlanResource extends JsonResource
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
            'description' => LanguageResolver::resolve($this->description, $language),
            'image' => LanguageResolver::resolve($this->image, $language),
            'thumbnail' => LanguageResolver::resolve($this->thumbnail, $language),
            'published_at' => $this->published_at?->toIso8601String(),
            'days' => ReadingPlanDayResource::collection($this->whenLoaded('days')),
        ];
    }
}
