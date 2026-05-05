<?php

declare(strict_types=1);

namespace App\Http\Resources\LanguageSettings;

use App\Domain\LanguageSettings\Models\LanguageSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public projection: slugs only, no internal IDs. Used by the frontend's
 * post-login modal and by mobile cold-start.
 *
 * @mixin LanguageSetting
 */
final class PublicLanguageSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'language' => $this->language,
            'default_bible_version' => $this->defaultBibleVersion === null
                ? null
                : ['abbreviation' => $this->defaultBibleVersion->abbreviation],
            'default_commentary' => $this->defaultCommentary === null
                ? null
                : ['slug' => $this->defaultCommentary->slug],
        ];
    }
}
