<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

use App\Domain\Bible\Models\BibleChapter;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Devotional\Models\Devotional;
use App\Domain\EducationalResources\Models\EducationalResource;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Domain\News\Models\News;
use App\Domain\QrCode\Models\QrCode;
use App\Domain\ReadingPlans\Models\ReadingPlanSubscription;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\Verses\Models\DailyVerse;
use Illuminate\Database\Eloquent\Model;

enum EventSubjectType: string
{
    case BibleChapter = 'bible_chapter';
    case Devotional = 'devotional';
    case SabbathSchoolLesson = 'sabbath_school_lesson';
    case EducationalResource = 'educational_resource';
    case ResourceBook = 'resource_book';
    case ResourceBookChapter = 'resource_book_chapter';
    case News = 'news';
    case HymnalSong = 'hymnal_song';
    case Commentary = 'commentary';
    case DailyVerse = 'daily_verse';
    case QrCode = 'qr_code';
    case ReadingPlanSubscription = 'reading_plan_subscription';

    /**
     * @return array<string, class-string<Model>>
     */
    public static function morphMap(): array
    {
        return [
            self::BibleChapter->value => BibleChapter::class,
            self::Devotional->value => Devotional::class,
            self::SabbathSchoolLesson->value => SabbathSchoolLesson::class,
            self::EducationalResource->value => EducationalResource::class,
            self::ResourceBook->value => ResourceBook::class,
            self::ResourceBookChapter->value => ResourceBookChapter::class,
            self::News->value => News::class,
            self::HymnalSong->value => HymnalSong::class,
            self::Commentary->value => Commentary::class,
            self::DailyVerse->value => DailyVerse::class,
            self::QrCode->value => QrCode::class,
            self::ReadingPlanSubscription->value => ReadingPlanSubscription::class,
        ];
    }
}
