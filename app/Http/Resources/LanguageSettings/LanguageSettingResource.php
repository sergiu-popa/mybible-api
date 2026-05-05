<?php

declare(strict_types=1);

namespace App\Http\Resources\LanguageSettings;

use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin shape for one language settings row. Exposes the full FK
 * relations so admins can render labels next to their identifiers.
 *
 * @mixin LanguageSetting
 */
final class LanguageSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'language' => $this->language,
            'default_bible_version' => $this->defaultBibleVersion === null ? null : [
                'id' => $this->defaultBibleVersion->id,
                'abbreviation' => $this->defaultBibleVersion->abbreviation,
                'name' => $this->defaultBibleVersion->name,
                'language' => $this->defaultBibleVersion->language,
            ],
            'default_commentary' => $this->defaultCommentary === null ? null : [
                'id' => $this->defaultCommentary->id,
                'slug' => $this->defaultCommentary->slug,
            ],
            'default_devotional_type' => $this->defaultDevotionalType === null ? null : [
                'id' => $this->defaultDevotionalType->id,
                'slug' => $this->defaultDevotionalType->slug,
                'title' => $this->defaultDevotionalType->title,
            ],
        ];
    }
}
