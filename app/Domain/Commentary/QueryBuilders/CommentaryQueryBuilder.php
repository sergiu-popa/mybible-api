<?php

declare(strict_types=1);

namespace App\Domain\Commentary\QueryBuilders;

use App\Domain\Commentary\Models\Commentary;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Commentary>
 */
final class CommentaryQueryBuilder extends Builder
{
    public function published(): self
    {
        return $this->where('is_published', true);
    }

    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }
}
