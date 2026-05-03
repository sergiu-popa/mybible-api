<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\QueryBuilders;

use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<OlympiadAttempt>
 */
final class OlympiadAttemptQueryBuilder extends Builder
{
    public function forUser(int $userId): self
    {
        return $this->where('user_id', $userId);
    }

    public function forFilters(?Language $language, ?string $book, ?string $chaptersLabel): self
    {
        if ($language !== null) {
            $this->where('language', $language->value);
        }
        if ($book !== null && $book !== '') {
            $this->where('book', $book);
        }
        if ($chaptersLabel !== null && $chaptersLabel !== '') {
            $this->where('chapters_label', $chaptersLabel);
        }

        return $this;
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('completed_at')->orderByDesc('id');
    }
}
