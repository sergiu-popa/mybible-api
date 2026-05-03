<?php

declare(strict_types=1);

namespace App\Domain\Devotional\QueryBuilders;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Devotional>
 */
final class DevotionalQueryBuilder extends Builder
{
    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function ofTypeId(int $typeId): self
    {
        return $this->where('type_id', $typeId);
    }

    public function onDate(CarbonImmutable $date): self
    {
        return $this->where('date', $date->toDateString());
    }

    public function publishedUpTo(CarbonImmutable $today): self
    {
        return $this->where('date', '<=', $today->toDateString());
    }

    public function withinRange(?CarbonImmutable $from, ?CarbonImmutable $to): self
    {
        if ($from !== null) {
            $this->where('date', '>=', $from->toDateString());
        }

        if ($to !== null) {
            $this->where('date', '<=', $to->toDateString());
        }

        return $this;
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('date')->orderByDesc('id');
    }
}
