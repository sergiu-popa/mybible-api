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

    /**
     * Eager-loads segments and their questions in a single query each, to
     * avoid N+1 on lesson detail responses. Verified by
     * ShowSabbathSchoolLessonTest::test_it_avoids_n_plus_one_on_a_large_fixture.
     */
    public function withLessonDetail(): self
    {
        return $this->with(['segments.questions']);
    }
}
