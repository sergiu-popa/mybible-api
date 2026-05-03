<?php

declare(strict_types=1);

namespace App\Domain\Devotional\QueryBuilders;

use App\Domain\Devotional\Models\DevotionalType;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<DevotionalType>
 */
final class DevotionalTypeQueryBuilder extends Builder
{
    public function forLanguage(Language $language): self
    {
        return $this->where(function (self $query) use ($language): void {
            $query->where('language', $language->value)->orWhereNull('language');
        });
    }

    public function forSlugAndLanguage(string $slug, Language $language): self
    {
        return $this->where('slug', $slug)
            ->where(function (self $query) use ($language): void {
                $query->where('language', $language->value)->orWhereNull('language');
            })
            ->orderByRaw('language IS NULL ASC')
            ->limit(1);
    }

    public function ordered(): self
    {
        return $this->orderBy('position')->orderBy('id');
    }
}
