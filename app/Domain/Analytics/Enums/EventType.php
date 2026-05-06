<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

enum EventType: string
{
    case BibleChapterViewed = 'bible.chapter.viewed';
    case BiblePassageViewed = 'bible.passage.viewed';
    case DevotionalViewed = 'devotional.viewed';
    case SabbathSchoolLessonViewed = 'sabbath_school.lesson.viewed';
    case ResourceViewed = 'resource.viewed';
    case ResourceDownloaded = 'resource.downloaded';
    case ResourceBookViewed = 'resource_book.viewed';
    case ResourceBookChapterViewed = 'resource_book.chapter.viewed';
    case ResourceBookDownloaded = 'resource_book.downloaded';
    case ResourceBookChapterDownloaded = 'resource_book.chapter.downloaded';
    case NewsViewed = 'news.viewed';
    case HymnalSongViewed = 'hymnal.song.viewed';
    case CommentaryViewed = 'commentary.viewed';
    case DailyVerseViewed = 'daily_verse.viewed';
    case QrCodeScanned = 'qr_code.scanned';
    case AuthLogin = 'auth.login';
    case ReadingPlanSubscriptionStarted = 'reading_plan.subscription.started';
    case ReadingPlanSubscriptionDayCompleted = 'reading_plan.subscription.day_completed';
    case ReadingPlanSubscriptionAbandoned = 'reading_plan.subscription.abandoned';
    case ReadingPlanSubscriptionCompleted = 'reading_plan.subscription.completed';

    /**
     * Subject morph alias the event must point at, or `null` for
     * subjectless events (auth.login, bible.passage.viewed — the
     * latter is keyed by metadata, not a single chapter).
     */
    public function expectedSubjectType(): ?EventSubjectType
    {
        return match ($this) {
            self::BibleChapterViewed => EventSubjectType::BibleChapter,
            self::DevotionalViewed => EventSubjectType::Devotional,
            self::SabbathSchoolLessonViewed => EventSubjectType::SabbathSchoolLesson,
            self::ResourceViewed => EventSubjectType::EducationalResource,
            self::ResourceDownloaded => EventSubjectType::EducationalResource,
            self::ResourceBookViewed => EventSubjectType::ResourceBook,
            self::ResourceBookDownloaded => EventSubjectType::ResourceBook,
            self::ResourceBookChapterViewed => EventSubjectType::ResourceBookChapter,
            self::ResourceBookChapterDownloaded => EventSubjectType::ResourceBookChapter,
            self::NewsViewed => EventSubjectType::News,
            self::HymnalSongViewed => EventSubjectType::HymnalSong,
            self::CommentaryViewed => EventSubjectType::Commentary,
            self::DailyVerseViewed => EventSubjectType::DailyVerse,
            self::QrCodeScanned => EventSubjectType::QrCode,
            self::ReadingPlanSubscriptionStarted,
            self::ReadingPlanSubscriptionDayCompleted,
            self::ReadingPlanSubscriptionAbandoned,
            self::ReadingPlanSubscriptionCompleted => EventSubjectType::ReadingPlanSubscription,
            self::BiblePassageViewed,
            self::AuthLogin => null,
        };
    }

    /**
     * Per-event-type validation rules applied to the `metadata` JSON
     * column. Empty array means metadata is optional and free-form.
     *
     * @return array<string, array<int, string>|string>
     */
    public function metadataRules(): array
    {
        return match ($this) {
            self::BibleChapterViewed => [
                'metadata' => ['required', 'array'],
                'metadata.version_abbreviation' => ['required', 'string', 'max:32'],
            ],
            self::BiblePassageViewed => [
                'metadata' => ['required', 'array'],
                'metadata.version_abbreviation' => ['required', 'string', 'max:32'],
                'metadata.reference' => ['required', 'string', 'max:128'],
            ],
            self::SabbathSchoolLessonViewed => [
                'metadata' => ['nullable', 'array'],
                'metadata.age_group' => ['nullable', 'string', 'max:32'],
            ],
            self::CommentaryViewed => [
                'metadata' => ['required', 'array'],
                'metadata.book' => ['required', 'string', 'max:32'],
                'metadata.chapter' => ['required', 'integer', 'min:1'],
            ],
            self::ReadingPlanSubscriptionStarted => [
                'metadata' => ['required', 'array'],
                'metadata.plan_id' => ['required', 'integer', 'min:1'],
                'metadata.plan_slug' => ['required', 'string', 'max:128'],
            ],
            self::ReadingPlanSubscriptionDayCompleted => [
                'metadata' => ['required', 'array'],
                'metadata.day_position' => ['required', 'integer', 'min:1'],
                'metadata.subscription_age_days' => ['required', 'integer', 'min:0'],
            ],
            self::ReadingPlanSubscriptionAbandoned => [
                'metadata' => ['required', 'array'],
                'metadata.at_day_position' => ['required', 'integer', 'min:1'],
                'metadata.total_days' => ['required', 'integer', 'min:1'],
            ],
            default => [
                'metadata' => ['nullable', 'array'],
            ],
        };
    }
}
