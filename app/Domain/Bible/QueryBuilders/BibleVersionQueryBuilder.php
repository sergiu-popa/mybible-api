<?php

declare(strict_types=1);

namespace App\Domain\Bible\QueryBuilders;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<BibleVersion>
 */
final class BibleVersionQueryBuilder extends Builder
{
    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }
}
