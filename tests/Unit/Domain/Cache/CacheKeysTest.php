<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cache;

use App\Domain\Bible\Support\BibleCacheKeys;
use App\Domain\Collections\Support\CollectionsCacheKeys;
use App\Domain\Devotional\Enums\DevotionalType;
use App\Domain\Devotional\Support\DevotionalCacheKeys;
use App\Domain\EducationalResources\Enums\ResourceType;
use App\Domain\EducationalResources\Support\EducationalResourcesCacheKeys;
use App\Domain\Mobile\Support\MobileCacheKeys;
use App\Domain\News\Support\NewsCacheKeys;
use App\Domain\Olympiad\Support\OlympiadCacheKeys;
use App\Domain\QrCode\Support\QrCodeCacheKeys;
use App\Domain\Reference\ChapterRange;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use App\Domain\Shared\Enums\Language;
use App\Domain\Verses\Support\VersesCacheKeys;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class CacheKeysTest extends TestCase
{
    public function test_sabbath_school_keys(): void
    {
        $this->assertSame('ss:lessons:list:en:p1:30', SabbathSchoolCacheKeys::lessonsList(Language::En, 1, 30));
        $this->assertSame('ss:lesson:42:ro', SabbathSchoolCacheKeys::lesson(42, Language::Ro));
        $this->assertSame(['ss', 'ss:lessons'], SabbathSchoolCacheKeys::tagsForLessonsList());
        $this->assertSame(['ss', 'ss:lesson:42'], SabbathSchoolCacheKeys::tagsForLesson(42));
    }

    public function test_devotional_keys(): void
    {
        $date = CarbonImmutable::parse('2026-04-29');
        $this->assertSame(
            'dev:ro:adults:2026-04-29',
            DevotionalCacheKeys::show(Language::Ro, DevotionalType::Adults, $date),
        );
        $this->assertSame(
            ['dev', 'dev:ro:adults'],
            DevotionalCacheKeys::tagsForDevotional(Language::Ro, DevotionalType::Adults),
        );
    }

    public function test_verses_keys(): void
    {
        $date = CarbonImmutable::parse('2026-04-29');
        $this->assertSame('verses:daily:2026-04-29', VersesCacheKeys::dailyVerse($date));
        $this->assertSame(['verses', 'daily-verse'], VersesCacheKeys::tagsForDailyVerse());
    }

    public function test_news_keys(): void
    {
        $this->assertSame('news:en:p2:20', NewsCacheKeys::list(Language::En, 2, 20));
        $this->assertSame(['news'], NewsCacheKeys::tagsForNews());
    }

    public function test_bible_keys(): void
    {
        $this->assertSame('bible:versions:list:en:p1:50', BibleCacheKeys::versionsList(Language::En, 1, 50));
        $this->assertSame('bible:versions:list:all:p1:50', BibleCacheKeys::versionsList(null, 1, 50));
        $this->assertSame('bible:export:VDC', BibleCacheKeys::versionExport('VDC'));
        $this->assertSame(['bible', 'bible:versions'], BibleCacheKeys::tagsForVersionList());
        $this->assertSame(['bible', 'bible:export:VDC'], BibleCacheKeys::tagsForExport('VDC'));
    }

    public function test_educational_resources_keys(): void
    {
        $this->assertSame(
            'edu:categories:ro:p1:50',
            EducationalResourcesCacheKeys::categories(Language::Ro, 1, 50),
        );
        $this->assertSame(
            'edu:cat:5:p2:25:all',
            EducationalResourcesCacheKeys::resourcesByCategory(5, 2, 25, null),
        );
        $this->assertSame(
            'edu:cat:5:p2:25:' . ResourceType::cases()[0]->value,
            EducationalResourcesCacheKeys::resourcesByCategory(5, 2, 25, ResourceType::cases()[0]),
        );
        $this->assertSame(['edu'], EducationalResourcesCacheKeys::tagsForCategoriesList());
        $this->assertSame(['edu', 'edu:cat:5'], EducationalResourcesCacheKeys::tagsForCategory(5));
    }

    public function test_collections_keys(): void
    {
        $this->assertSame('col:topics:en:p1:15', CollectionsCacheKeys::topicsList(Language::En, 1, 15));
        $this->assertSame('col:topic:7:en', CollectionsCacheKeys::topic(7, Language::En));
        $this->assertSame(['col'], CollectionsCacheKeys::tagsForTopicsList());
        $this->assertSame(['col', 'col:topic:7'], CollectionsCacheKeys::tagsForTopic(7));
    }

    public function test_olympiad_keys(): void
    {
        $range = new ChapterRange(1, 3);
        $this->assertSame('oly:themes:en:p1:50', OlympiadCacheKeys::themesList(Language::En, 1, 50));
        $this->assertSame(
            'oly:theme:GEN:1-3:en',
            OlympiadCacheKeys::themeQuestions('GEN', $range, Language::En),
        );
        $this->assertSame(['oly'], OlympiadCacheKeys::tagsForThemesList());
        $this->assertSame(
            ['oly', 'oly:theme:GEN:1-3:en'],
            OlympiadCacheKeys::tagsForTheme('GEN', $range, Language::En),
        );
    }

    public function test_qr_code_keys(): void
    {
        $this->assertSame('qr:GEN.1:1.VDC', QrCodeCacheKeys::show('GEN.1:1.VDC'));
        $this->assertSame(['qr'], QrCodeCacheKeys::tagsForQr());
    }

    public function test_mobile_bootstrap_keys_per_language(): void
    {
        $this->assertSame('app:bootstrap:en', MobileCacheKeys::bootstrap(Language::En));
        $this->assertSame('app:bootstrap:ro', MobileCacheKeys::bootstrap(Language::Ro));
        $this->assertSame('app:bootstrap:hu', MobileCacheKeys::bootstrap(Language::Hu));
    }

    public function test_mobile_bootstrap_tag_union(): void
    {
        $tags = MobileCacheKeys::tagsForBootstrap();

        $this->assertSame(
            ['app:bootstrap', 'news', 'daily-verse', 'dev', 'ss', 'ss:lessons', 'bible', 'bible:versions', 'qr'],
            $tags,
            'Bootstrap tag union must include every constituent tag so any flush propagates.',
        );
    }
}
