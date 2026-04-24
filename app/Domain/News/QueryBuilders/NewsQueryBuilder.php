<?php

declare(strict_types=1);

namespace App\Domain\News\QueryBuilders;

use App\Domain\News\Models\News;
use App\Domain\Shared\Enums\Language;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<News>
 */
final class NewsQueryBuilder extends Builder
{
    public function published(?CarbonImmutable $now = null): self
    {
        $cutoff = ($now ?? CarbonImmutable::now())->toDateTimeString();

        return $this
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $cutoff);
    }

    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('published_at')->orderByDesc('id');
    }
}
