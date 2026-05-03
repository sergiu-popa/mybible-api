<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\QueryBuilders;

use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\Shared\Enums\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<SabbathSchoolLesson>
 */
final class SabbathSchoolLessonQueryBuilder extends Builder
{
    public function published(): self
    {
        return $this
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function forLanguage(Language $language): self
    {
        return $this->where('language', $language->value);
    }

    public function forTrimester(int $trimesterId): self
    {
        return $this->where('trimester_id', $trimesterId);
    }

    public function forAgeGroup(string $ageGroup): self
    {
        return $this->where('age_group', $ageGroup);
    }

    /**
     * Eager-loads segments and their typed content blocks in a single
     * query each, to avoid N+1 on lesson detail responses.
     */
    public function withLessonDetail(): self
    {
        return $this->with(['segments.segmentContents']);
    }
}
