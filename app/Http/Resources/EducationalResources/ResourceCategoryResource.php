<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceCategory;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResourceCategory
 */
final class ResourceCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $language = $request->attributes->get(ResolveRequestLanguage::ATTRIBUTE_KEY, Language::En);

        if (! $language instanceof Language) {
            $language = Language::En;
        }

        return [
            'id' => $this->id,
            'name' => LanguageResolver::resolve($this->name, $language),
            'description' => LanguageResolver::resolve($this->description ?? [], $language),
            'language' => $this->language,
            'resource_count' => (int) ($this->resource_count ?? 0),
        ];
    }
}
