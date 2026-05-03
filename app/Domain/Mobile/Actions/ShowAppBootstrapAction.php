<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Actions;

use App\Domain\Bible\Actions\ListBibleVersionsAction;
use App\Domain\Devotional\Actions\FetchDevotionalAction;
use App\Domain\Devotional\Actions\ResolveDevotionalTypeAction;
use App\Domain\Devotional\DataTransferObjects\FetchDevotionalData;
use App\Domain\Mobile\Support\MobileCacheKeys;
use App\Domain\News\Actions\ListNewsAction;
use App\Domain\QrCode\Actions\ListQrCodesAction;
use App\Domain\SabbathSchool\Models\SabbathSchoolLesson;
use App\Domain\Shared\Enums\Language;
use App\Domain\Verses\Actions\GetDailyVerseAction;
use App\Domain\Verses\Exceptions\NoDailyVerseForDateException;
use App\Support\Caching\CachedRead;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ShowAppBootstrapAction
{
    public function __construct(
        private readonly CachedRead $cache,
        private readonly ListNewsAction $listNews,
        private readonly GetDailyVerseAction $getDailyVerse,
        private readonly FetchDevotionalAction $fetchDevotional,
        private readonly ResolveDevotionalTypeAction $resolveDevotionalType,
        private readonly ListBibleVersionsAction $listBibleVersions,
        private readonly ListQrCodesAction $listQrCodes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Language $language): array
    {
        $ttl = (int) config('mobile.bootstrap.cache_ttl', 300);

        $result = $this->cache->read(
            MobileCacheKeys::bootstrap($language),
            MobileCacheKeys::tagsForBootstrap(),
            $ttl,
            function () use ($language): array {
                return $this->build($language);
            },
        );

        $this->tagSentryColdStart();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Language $language): array
    {
        $today = CarbonImmutable::today();

        $dailyVerse = null;
        try {
            $data = $this->getDailyVerse->handle($today);
            $dailyVerse = $data['data'] ?? $data;
        } catch (NoDailyVerseForDateException) {
            $dailyVerse = null;
        }

        $news = $this->listNews->execute($language, 1, 7);
        $newsItems = $news['data'] ?? [];

        $bibleVersions = $this->listBibleVersions->execute($language, 1, 100);
        $bibleVersionItems = $bibleVersions['data'] ?? [];

        $adultsDevotional = $this->fetchDevotionalBySlug('adults', $language, $today);
        $kidsDevotional = $this->fetchDevotionalBySlug('kids', $language, $today);

        $currentLesson = SabbathSchoolLesson::query()
            ->published()
            ->forLanguage($language)
            ->where('date_from', '<=', $today->toDateString())
            ->where('date_to', '>=', $today->toDateString())
            ->latest('published_at')
            ->first();

        if ($currentLesson === null) {
            $currentLesson = SabbathSchoolLesson::query()
                ->published()
                ->forLanguage($language)
                ->latest('published_at')
                ->first();
        }

        $qrCodes = $this->listQrCodes->execute();
        $qrCodeItems = $qrCodes['data'] ?? [];

        return [
            'version' => [
                'ios' => config('mobile.ios.latest_version'),
                'android' => config('mobile.android.latest_version'),
            ],
            'languages_available' => array_map(
                static fn (Language $l): string => $l->value,
                Language::cases(),
            ),
            'daily_verse' => $dailyVerse,
            'news' => $newsItems,
            'bible_versions' => $bibleVersionItems,
            'devotionals_today' => [
                'adults' => $adultsDevotional,
                'youth' => $kidsDevotional,
            ],
            'sabbath_school_current_lesson' => $currentLesson !== null
                ? [
                    'id' => $currentLesson->id,
                    'title' => $currentLesson->title,
                    'language' => $currentLesson->language,
                    'week_start' => $currentLesson->date_from->toDateString(),
                    'week_end' => $currentLesson->date_to->toDateString(),
                ]
                : null,
            'qr_codes' => $qrCodeItems,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDevotionalBySlug(string $slug, Language $language, CarbonImmutable $today): ?array
    {
        try {
            $type = $this->resolveDevotionalType->handle($slug, $language);
        } catch (ModelNotFoundException) {
            return null;
        }

        try {
            $data = $this->fetchDevotional->execute(new FetchDevotionalData(
                language: $language,
                typeId: $type->id,
                typeSlug: $type->slug,
                date: $today,
            ));

            return $data['data'] ?? $data;
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function tagSentryColdStart(): void
    {
        if (! function_exists('Sentry\configureScope')) {
            return;
        }

        \Sentry\configureScope(function ($scope): void {
            $scope->setTag('cold_start', 'true');
        });
    }
}
