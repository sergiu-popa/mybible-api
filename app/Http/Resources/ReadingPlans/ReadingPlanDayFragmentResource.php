<?php

declare(strict_types=1);

namespace App\Http\Resources\ReadingPlans;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use App\Domain\ReadingPlans\Support\LanguageResolver;
use App\Domain\Shared\Enums\Language;
use App\Http\Middleware\ResolveRequestLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReadingPlanDayFragment
 */
final class ReadingPlanDayFragmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'type' => $this->type->value,
            'content' => $this->resolveContent(),
        ];
    }

    /**
     * @return string|array<int, string>|null
     */
    private function resolveContent(): string|array|null
    {
        if ($this->type === FragmentType::References) {
            /** @var array<int, string> $content */
            $content = $this->content;

            return array_values($content);
        }

        /** @var Language $language */
        $language = app(ResolveRequestLanguage::CONTAINER_KEY);

        /** @var array<string, mixed> $content */
        $content = $this->content;

        return LanguageResolver::resolve($content, $language);
    }
}
