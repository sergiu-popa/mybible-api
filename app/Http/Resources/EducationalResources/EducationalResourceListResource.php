<?php

declare(strict_types=1);

namespace App\Http\Resources\EducationalResources;

use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Support\MediaUrlResolver;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EducationalResource
 */
final class EducationalResourceListResource extends JsonResource
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

        $disk = (string) config('educational_resources.media_disk', 'public');

        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'title' => LanguageResolver::resolve($this->title, $language),
            'summary' => LanguageResolver::resolve($this->summary ?? [], $language),
            'thumbnail_url' => MediaUrlResolver::absoluteUrl($this->thumbnail_path, $disk),
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}
