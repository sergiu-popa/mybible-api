<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the "Bible in a Year — 365-Day Reading Plan" (YouVersion plan #19038).
 *
 * Idempotent: re-running the seeder updates the plan metadata, deletes its
 * existing days (and their fragments via cascade), and rebuilds them. Safe to
 * run in production via:
 *   php artisan db:seed --class=BibleInAYearReadingPlanSeeder --force
 */
final class BibleInAYearReadingPlanSeeder extends Seeder
{
    private const SLUG = 'bible-in-a-year-365-day';

    public function run(): void
    {
        DB::transaction(function (): void {
            $plan = ReadingPlan::query()->updateOrCreate(
                ['slug' => self::SLUG],
                [
                    'name' => [
                        'en' => 'Bible in a Year — 365-Day Reading Plan',
                        'ro' => 'Biblia într-un an — plan de 365 de zile',
                    ],
                    'description' => [
                        'en' => 'Read through the entire Bible in 365 days. Each day combines a passage from the Old Testament, the New Testament, the Psalms, and Proverbs.',
                        'ro' => 'Citește întreaga Biblie în 365 de zile. Fiecare zi îmbină un pasaj din Vechiul Testament, Noul Testament, Psalmi și Proverbe.',
                    ],
                    'image' => [
                        'en' => 'https://placehold.co/1200x630?text=Bible+in+a+Year',
                        'ro' => 'https://placehold.co/1200x630?text=Biblia+intr-un+an',
                    ],
                    'thumbnail' => [
                        'en' => 'https://placehold.co/400x400?text=Bible+in+a+Year',
                        'ro' => 'https://placehold.co/400x400?text=Biblia+intr-un+an',
                    ],
                    'status' => ReadingPlanStatus::Published,
                    'published_at' => now(),
                ],
            );

            $plan->days()->delete();

            foreach (self::DAYS as $position => $references) {
                $day = ReadingPlanDay::query()->create([
                    'reading_plan_id' => $plan->id,
                    'position' => $position,
                ]);

                $fragmentPosition = 1;
                foreach (self::parseReferences($references) as $reference) {
                    ReadingPlanDayFragment::query()->create([
                        'reading_plan_day_id' => $day->id,
                        'position' => $fragmentPosition++,
                        'type' => FragmentType::References,
                        'content' => [$reference],
                    ]);
                }
            }
        });
    }

    /**
     * Splits a day line into one entry per chapter/passage so each reference
     * becomes its own fragment row (and tappable item in the UI). The
     * top-level separator is comma; ranges that span multiple chapters of
     * the same book (e.g. "GEN.1-3") are expanded into individual chapters.
     *
     * @return list<string>
     */
    private static function parseReferences(string $line): array
    {
        $references = [];

        foreach (explode(',', $line) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            foreach (self::expandChapterRange($entry) as $reference) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * Expands a chapter-range reference like "GEN.1-3" into ["GEN.1", "GEN.2",
     * "GEN.3"]. Verse-scoped references (e.g. "MAT.2:1-12") and single-chapter
     * references (e.g. "GEN.1") are returned untouched.
     *
     * @return list<string>
     */
    private static function expandChapterRange(string $reference): array
    {
        if (! preg_match('/^([A-Z0-9]+)\.(\d+)-(\d+)$/', $reference, $matches)) {
            return [$reference];
        }

        [$book, $start, $end] = [$matches[1], (int) $matches[2], (int) $matches[3]];

        if ($end < $start) {
            return [$reference];
        }

        $expanded = [];
        for ($chapter = $start; $chapter <= $end; $chapter++) {
            $expanded[] = "{$book}.{$chapter}";
        }

        return $expanded;
    }

    /**
     * Day-by-day references for "The One Year Bible" (YouVersion plan #60).
     * Scraped from bible.com/reading-plans/60-the-one-year-bible on 2026-04-28
     * — one entry per Scripture link shown on each day's page.
     *
     * @var array<int, string>
     */
    private const DAYS = [
        1 => 'GEN.1,GEN.2,MAT.1,MAT.2:1-12,PSA.1:1-6,PRO.1:1-6',
        2 => 'GEN.3,GEN.4,MAT.2:13-23,MAT.3:1-6,PSA.2:1-12,PRO.1:7-9',
        3 => 'GEN.5,GEN.6,GEN.7,MAT.3:7-17,MAT.4:1-11,PSA.3:1-8,PRO.1:10-19',
        4 => 'GEN.8,GEN.9,GEN.10,MAT.4:12-25,PSA.4:1-8,PRO.1:20-23',
        5 => 'GEN.11,GEN.12,GEN.13:1-4,MAT.5:1-26,PSA.5:1-12,PRO.1:24-28',
        6 => 'GEN.13:5-18,GEN.14,GEN.15,MAT.5:27-48,PSA.6:1-10,PRO.1:29-33',
        7 => 'GEN.16,GEN.17,GEN.18:1-15,MAT.6:1-24,PSA.7:1-17,PRO.2:1-5',
        8 => 'GEN.18:16-33,GEN.19,MAT.6:25-34,MAT.7:1-14,PSA.8:1-9,PRO.2:6-15',
        9 => 'GEN.20,GEN.21,GEN.22,MAT.7:15-29,PSA.9:1-12,PRO.2:16-22',
        10 => 'GEN.23,GEN.24:1-51,MAT.8:1-17,PSA.9:13-20,PRO.3:1-6',
        11 => 'GEN.24:52-67,GEN.25,GEN.26:1-16,MAT.8:18-34,PSA.10:1-15,PRO.3:7-8',
        12 => 'GEN.26:17-35,GEN.27,MAT.9:1-17,PSA.10:16-18,PRO.3:9-10',
        13 => 'GEN.28,GEN.29,MAT.9:18-38,PSA.11:1-7,PRO.3:11-12',
        14 => 'GEN.30,GEN.31:1-16,MAT.10:1-23,PSA.12:1-8,PRO.3:13-15',
        15 => 'GEN.31:17-55,GEN.32:1-12,MAT.10:24-42,MAT.11:1-6,PSA.13:1-6,PRO.3:16-18',
        16 => 'GEN.32:13-32,GEN.33,GEN.34,MAT.11:7-30,PSA.14:1-7,PRO.3:19-20',
        17 => 'GEN.35,GEN.36,MAT.12:1-21,PSA.15:1-5,PRO.3:21-26',
        18 => 'GEN.37,GEN.38,MAT.12:22-45,PSA.16:1-11,PRO.3:27-32',
        19 => 'GEN.39,GEN.40,GEN.41:1-16,MAT.12:46-50,MAT.13:1-23,PSA.17:1-15,PRO.3:33-35',
        20 => 'GEN.41:17-57,GEN.42:1-17,MAT.13:24-46,PSA.18:1-15,PRO.4:1-6',
        21 => 'GEN.42:18-38,GEN.43,MAT.13:47-58,MAT.14:1-12,PSA.18:16-36,PRO.4:7-10',
        22 => 'GEN.44,GEN.45,MAT.14:13-36,PSA.18:37-50,PRO.4:11-13',
        23 => 'GEN.46,GEN.47,MAT.15:1-28,PSA.19:1-14,PRO.4:14-19',
        24 => 'GEN.48,GEN.49,MAT.15:29-39,MAT.16:1-12,PSA.20:1-9,PRO.4:20-27',
        25 => 'GEN.50,EXO.1,EXO.2:1-10,MAT.16:13-28,MAT.17:1-9,PSA.21:1-13,PRO.5:1-6',
        26 => 'EXO.2:11-25,EXO.3,MAT.17:10-27,PSA.22:1-18,PRO.5:7-14',
        27 => 'EXO.4,EXO.5:1-21,MAT.18:1-20,PSA.22:19-31,PRO.5:15-21',
        28 => 'EXO.5:22-23,EXO.6,EXO.7,MAT.18:21-35,MAT.19:1-12,PSA.23:1-6,PRO.5:22-23',
        29 => 'EXO.8,EXO.9,MAT.19:13-30,PSA.24:1-10,PRO.6:1-5',
        30 => 'EXO.10,EXO.11,EXO.12:1-13,MAT.20:1-28,PSA.25:1-15,PRO.6:6-11',
        31 => 'EXO.12:14-51,EXO.13:1-16,MAT.20:29-34,MAT.21:1-22,PSA.25:16-22,PRO.6:12-15',
        32 => 'EXO.13:17-22,EXO.14,EXO.15:1-18,MAT.21:23-46,PSA.26:1-12,PRO.6:16-19',
        33 => 'EXO.15:19-27,EXO.16,EXO.17:1-7,MAT.22:1-33,PSA.27:1-6,PRO.6:20-26',
        34 => 'EXO.17:8-16,EXO.18,EXO.19:1-15,MAT.22:34-46,MAT.23:1-12,PSA.27:7-14,PRO.6:27-35',
        35 => 'EXO.19:16-25,EXO.20,EXO.21:1-21,MAT.23:13-39,PSA.28:1-9,PRO.7:1-5',
        36 => 'EXO.21:22-36,EXO.22,EXO.23:1-13,MAT.24:1-28,PSA.29:1-11,PRO.7:6-23',
        37 => 'EXO.23:14-33,EXO.24,EXO.25,MAT.24:29-51,PSA.30:1-12,PRO.7:24-27',
        38 => 'EXO.26,EXO.27,MAT.25:1-30,PSA.31:1-8,PRO.8:1-11',
        39 => 'EXO.28,MAT.25:31-46,MAT.26:1-13,PSA.31:9-18,PRO.8:12-13',
        40 => 'EXO.29,EXO.30:1-10,MAT.26:14-46,PSA.31:19-24,PRO.8:14-26',
        41 => 'EXO.30:11-38,EXO.31,MAT.26:47-68,PSA.32:1-11,PRO.8:27-32',
        42 => 'EXO.32,EXO.33,MAT.26:69-75,MAT.27:1-14,PSA.33:1-11,PRO.8:33-36',
        43 => 'EXO.34,EXO.35:1-9,MAT.27:15-31,PSA.33:12-22,PRO.9:1-6',
        44 => 'EXO.35:10-35,EXO.36,MAT.27:32-66,PSA.34:1-10,PRO.9:7-8',
        45 => 'EXO.37,EXO.38,MAT.28,PSA.34:11-22,PRO.9:9-10',
        46 => 'EXO.39,EXO.40,MRK.1:1-28,PSA.35:1-16,PRO.9:11-12',
        47 => 'LEV.1,LEV.2,LEV.3,MRK.1:29-45,MRK.2:1-12,PSA.35:17-28,PRO.9:13-18',
        48 => 'LEV.4,LEV.5,MRK.2:13-28,MRK.3:1-6,PSA.36:1-12,PRO.10:1-2',
        49 => 'LEV.6,LEV.7:1-27,MRK.3:7-30,PSA.37:1-11,PRO.10:3-4',
        50 => 'LEV.7:28-38,LEV.8,LEV.9:1-6,MRK.3:31-35,MRK.4:1-25,PSA.37:12-29,PRO.10:5',
        51 => 'LEV.9:7-24,LEV.10,MRK.4:26-41,MRK.5:1-20,PSA.37:30-40,PRO.10:6-7',
        52 => 'LEV.11,LEV.12,MRK.5:21-43,PSA.38:1-22,PRO.10:8-9',
        53 => 'LEV.13,MRK.6:1-29,PSA.39:1-13,PRO.10:10',
        54 => 'LEV.14,MRK.6:30-56,PSA.40:1-10,PRO.10:11-12',
        55 => 'LEV.15,LEV.16:1-28,MRK.7:1-23,PSA.40:11-17,PRO.10:13-14',
        56 => 'LEV.16:29-34,LEV.17,LEV.18,MRK.7:24-37,MRK.8:1-10,PSA.41:1-13,PRO.10:15-16',
        57 => 'LEV.19,LEV.20:1-21,MRK.8:11-38,PSA.42:1-11,PRO.10:17',
        58 => 'LEV.20:22-27,LEV.21,LEV.22:1-16,MRK.9:1-29,PSA.43:1-5,PRO.10:18',
        59 => 'LEV.22:17-33,LEV.23,MRK.9:30-50,MRK.10:1-12,PSA.44:1-8,PRO.10:19',
        60 => 'LEV.24,LEV.25:1-46,MRK.10:13-31,PSA.44:9-26,PRO.10:20-21',
        61 => 'LEV.25:47-55,LEV.26,LEV.27:1-13,MRK.10:32-52,PSA.45:1-17,PRO.10:22',
        62 => 'LEV.27:14-34,NUM.1,MRK.11:1-26,PSA.46:1-11,PRO.10:23',
        63 => 'NUM.2,NUM.3,MRK.11:27-33,MRK.12:1-17,PSA.47:1-9,PRO.10:24-25',
        64 => 'NUM.4,NUM.5,MRK.12:18-37,PSA.48:1-14,PRO.10:26',
        65 => 'NUM.6,NUM.7,MRK.12:38-44,MRK.13:1-13,PSA.49:1-20,PRO.10:27-28',
        66 => 'NUM.8,NUM.9,MRK.13:14-37,PSA.50:1-23,PRO.10:29-30',
        67 => 'NUM.10,NUM.11:1-23,MRK.14:1-21,PSA.51:1-19,PRO.10:31-32',
        68 => 'NUM.11:24-35,NUM.12,NUM.13,MRK.14:22-52,PSA.52:1-9,PRO.11:1-3',
        69 => 'NUM.14,NUM.15:1-16,MRK.14:53-72,PSA.53:1-6,PRO.11:4',
        70 => 'NUM.15:17-41,NUM.16:1-40,MRK.15,PSA.54:1-7,PRO.11:5-6',
        71 => 'NUM.16:41-50,NUM.17,NUM.18,MRK.16,PSA.55:1-23,PRO.11:7',
        72 => 'NUM.19,NUM.20,LUK.1:1-25,PSA.56:1-13,PRO.11:8',
        73 => 'NUM.21,NUM.22:1-20,LUK.1:26-56,PSA.57:1-11,PRO.11:9-11',
        74 => 'NUM.22:21-41,NUM.23,LUK.1:57-80,PSA.58:1-11,PRO.11:12-13',
        75 => 'NUM.24,NUM.25,LUK.2:1-35,PSA.59:1-17,PRO.11:14',
        76 => 'NUM.26:1-51,LUK.2:36-52,PSA.60:1-12,PRO.11:15',
        77 => 'NUM.26:52-65,NUM.27,NUM.28:1-15,LUK.3:1-22,PSA.61:1-8,PRO.11:16-17',
        78 => 'NUM.28:16-31,NUM.29,LUK.3:23-38,PSA.62:1-12,PRO.11:18-19',
        79 => 'NUM.30,NUM.31,LUK.4:1-30,PSA.63:1-11,PRO.11:20-21',
        80 => 'NUM.32,NUM.33:1-39,LUK.4:31-44,LUK.5:1-11,PSA.64:1-10,PRO.11:22',
        81 => 'NUM.33:40-56,NUM.34,NUM.35,LUK.5:12-28,PSA.65:1-13,PRO.11:23',
        82 => 'NUM.36,DEU.1,LUK.5:29-39,LUK.6:1-11,PSA.66:1-20,PRO.11:24-26',
        83 => 'DEU.2,DEU.3,LUK.6:12-38,PSA.67:1-7,PRO.11:27',
        84 => 'DEU.4,LUK.6:39-49,LUK.7:1-10,PSA.68:1-18,PRO.11:28',
        85 => 'DEU.5,DEU.6,LUK.7:11-35,PSA.68:19-35,PRO.11:29-31',
        86 => 'DEU.7,DEU.8,LUK.7:36-50,LUK.8:1-3,PSA.69:1-18,PRO.12:1',
        87 => 'DEU.9,DEU.10,LUK.8:4-21,PSA.69:19-36,PRO.12:2-3',
        88 => 'DEU.11,DEU.12,LUK.8:22-39,PSA.70:1-5,PRO.12:4',
        89 => 'DEU.13,DEU.14,DEU.15,LUK.8:40-56,LUK.9:1-6,PSA.71:1-24,PRO.12:5-7',
        90 => 'DEU.16,DEU.17,LUK.9:7-27,PSA.72:1-20,PRO.12:8-9',
        91 => 'DEU.18,DEU.19,DEU.20,LUK.9:28-50,PSA.73:1-28,PRO.12:10',
        92 => 'DEU.21,DEU.22,LUK.9:51-62,LUK.10:1-12,PSA.74:1-23,PRO.12:11',
        93 => 'DEU.23,DEU.24,DEU.25,LUK.10:13-37,PSA.75:1-10,PRO.12:12-14',
        94 => 'DEU.26,DEU.27,LUK.10:38-42,LUK.11:1-13,PSA.76:1-12,PRO.12:15-17',
        95 => 'DEU.28,LUK.11:14-36,PSA.77:1-20,PRO.12:18',
        96 => 'DEU.29,DEU.30,LUK.11:37-54,LUK.12:1-7,PSA.78:1-31,PRO.12:19-20',
        97 => 'DEU.31,DEU.32:1-27,LUK.12:8-34,PSA.78:32-55,PRO.12:21-23',
        98 => 'DEU.32:28-52,LUK.12:35-59,PSA.78:56-64,PRO.12:24',
        99 => 'DEU.33,LUK.13:1-21,PSA.78:65-72,PRO.12:25',
        100 => 'DEU.34,JOS.1,JOS.2,LUK.13:22-35,LUK.14:1-6,PSA.79:1-13,PRO.12:26',
        101 => 'JOS.3,JOS.4,LUK.14:7-35,PSA.80:1-19,PRO.12:27-28',
        102 => 'JOS.5,JOS.6,JOS.7:1-15,LUK.15,PSA.81:1-16,PRO.13:1',
        103 => 'JOS.7:16-26,JOS.8,JOS.9:1-2,LUK.16:1-18,PSA.82:1-8,PRO.13:2-3',
        104 => 'JOS.9:3-27,JOS.10,LUK.16:19-31,LUK.17:1-10,PSA.83:1-18,PRO.13:4',
        105 => 'JOS.11,JOS.12,LUK.17:11-37,PSA.84:1-12,PRO.13:5-6',
        106 => 'JOS.13,JOS.14,LUK.18:1-17,PSA.85:1-13,PRO.13:7-8',
        107 => 'JOS.15,LUK.18:18-43,PSA.86:1-17,PRO.13:9-10',
        108 => 'JOS.16,JOS.17,JOS.18,LUK.19:1-27,PSA.87:1-7,PRO.13:11',
        109 => 'JOS.19,JOS.20,LUK.19:28-48,PSA.88:1-18,PRO.13:12-14',
        110 => 'JOS.21,JOS.22:1-20,LUK.20:1-26,PSA.89:1-13,PRO.13:15-16',
        111 => 'JOS.22:21-34,JOS.23,LUK.20:27-47,PSA.89:14-37,PRO.13:17-19',
        112 => 'JOS.24,LUK.21:1-28,PSA.89:38-52,PRO.13:20-23',
        113 => 'JDG.1,JDG.2:1-9,LUK.21:29-38,LUK.22:1-13,PSA.90,PSA.91:1-16,PRO.13:24-25',
        114 => 'JDG.2:10-23,JDG.3,LUK.22:14-34,PSA.92,PSA.93:1-5,PRO.14:1-2',
        115 => 'JDG.4,JDG.5,LUK.22:35-53,PSA.94:1-23,PRO.14:3-4',
        116 => 'JDG.6,LUK.22:54-71,LUK.23:1-12,PSA.95,PSA.96:1-13,PRO.14:5-6',
        117 => 'JDG.7,JDG.8:1-17,LUK.23:13-43,PSA.97,PSA.98:1-9,PRO.14:7-8',
        118 => 'JDG.8:18-35,JDG.9:1-21,LUK.23:44-56,LUK.24:1-12,PSA.99:1-9,PRO.14:9-10',
        119 => 'JDG.9:22-57,JDG.10,LUK.24:13-53,PSA.100:1-5,PRO.14:11-12',
        120 => 'JDG.11,JDG.12,JHN.1:1-28,PSA.101:1-8,PRO.14:13-14',
        121 => 'JDG.13,JDG.14,JHN.1:29-51,PSA.102:1-28,PRO.14:15-16',
        122 => 'JDG.15,JDG.16,JHN.2,PSA.103:1-22,PRO.14:17-19',
        123 => 'JDG.17,JDG.18,JHN.3:1-21,PSA.104:1-23,PRO.14:20-21',
        124 => 'JDG.19,JDG.20,JHN.3:22-36,JHN.4:1-3,PSA.104:24-35,PRO.14:22-24',
        125 => 'JDG.21,RUT.1,JHN.4:4-42,PSA.105:1-15,PRO.14:25',
        126 => 'RUT.2,RUT.3,RUT.4,JHN.4:43-54,PSA.105:16-36,PRO.14:26-27',
        127 => '1SA.1,1SA.2:1-21,JHN.5:1-23,PSA.105:37-45,PRO.14:28-29',
        128 => '1SA.2:22-36,1SA.3,1SA.4,JHN.5:24-47,PSA.106:1-12,PRO.14:30-31',
        129 => '1SA.5,1SA.6,1SA.7,JHN.6:1-21,PSA.106:13-31,PRO.14:32-33',
        130 => '1SA.8,1SA.9,JHN.6:22-42,PSA.106:32-48,PRO.14:34-35',
        131 => '1SA.10,1SA.11,JHN.6:43-71,PSA.107:1-43,PRO.15:1-3',
        132 => '1SA.12,1SA.13,JHN.7:1-30,PSA.108:1-13,PRO.15:4',
        133 => '1SA.14,JHN.7:31-53,PSA.109:1-31,PRO.15:5-7',
        134 => '1SA.15,1SA.16,JHN.8:1-20,PSA.110:1-7,PRO.15:8-10',
        135 => '1SA.17,1SA.18:1-4,JHN.8:21-30,PSA.111:1-10,PRO.15:11',
        136 => '1SA.18:5-30,1SA.19,JHN.8:31-59,PSA.112:1-10,PRO.15:12-14',
        137 => '1SA.20,1SA.21,JHN.9,PSA.113,PSA.114:1-8,PRO.15:15-17',
        138 => '1SA.22,1SA.23,JHN.10:1-21,PSA.115:1-18,PRO.15:18-19',
        139 => '1SA.24,1SA.25,JHN.10:22-42,PSA.116:1-19,PRO.15:20-21',
        140 => '1SA.26,1SA.27,1SA.28,JHN.11:1-54,PSA.117:1-2,PRO.15:22-23',
        141 => '1SA.29,1SA.30,1SA.31,JHN.11:55-57,JHN.12:1-19,PSA.118:1-18,PRO.15:24-26',
        142 => '2SA.1,2SA.2:1-11,JHN.12:20-50,PSA.118:19-29,PRO.15:27-28',
        143 => '2SA.2:12-32,2SA.3,JHN.13:1-30,PSA.119:1-16,PRO.15:29-30',
        144 => '2SA.4,2SA.5,2SA.6,JHN.13:31-38,JHN.14:1-14,PSA.119:17-32,PRO.15:31-32',
        145 => '2SA.7,2SA.8,JHN.14:15-31,PSA.119:33-48,PRO.15:33',
        146 => '2SA.9,2SA.10,2SA.11,JHN.15,PSA.119:49-64,PRO.16:1-3',
        147 => '2SA.12,JHN.16,PSA.119:65-80,PRO.16:4-5',
        148 => '2SA.13,JHN.17,PSA.119:81-96,PRO.16:6-7',
        149 => '2SA.14,2SA.15:1-22,JHN.18:1-24,PSA.119:97-112,PRO.16:8-9',
        150 => '2SA.15:23-37,2SA.16,JHN.18:25-40,JHN.19:1-22,PSA.119:113-128,PRO.16:10-11',
        151 => '2SA.17,JHN.19:23-42,PSA.119:129-152,PRO.16:12-13',
        152 => '2SA.18,2SA.19:1-10,JHN.20:1-31,PSA.119:153-176,PRO.16:14-15',
        153 => '2SA.19:11-43,2SA.20:1-13,JHN.21,PSA.120:1-7,PRO.16:16-17',
        154 => '2SA.20:14-26,2SA.21,ACT.1,PSA.121:1-8,PRO.16:18',
        155 => '2SA.22,2SA.23:1-23,ACT.2,PSA.122:1-9,PRO.16:19-20',
        156 => '2SA.23:24-39,2SA.24,ACT.3,PSA.123:1-4,PRO.16:21-23',
        157 => '1KI.1,ACT.4,PSA.124:1-8,PRO.16:24',
        158 => '1KI.2,1KI.3:1-2,ACT.5,PSA.125:1-5,PRO.16:25',
        159 => '1KI.3:3-28,1KI.4,ACT.6,PSA.126:1-6,PRO.16:26-27',
        160 => '1KI.5,1KI.6,ACT.7:1-29,PSA.127:1-5,PRO.16:28-30',
        161 => '1KI.7,ACT.7:30-50,PSA.128:1-6,PRO.16:31-33',
        162 => '1KI.8,ACT.7:51-60,ACT.8:1-13,PSA.129:1-8,PRO.17:1',
        163 => '1KI.9,1KI.10,ACT.8:14-40,PSA.130:1-8,PRO.17:2-3',
        164 => '1KI.11,1KI.12:1-19,ACT.9:1-25,PSA.131:1-3,PRO.17:4-5',
        165 => '1KI.12:20-33,1KI.13,ACT.9:26-43,PSA.132:1-18,PRO.17:6',
        166 => '1KI.14,1KI.15:1-24,ACT.10:1-23,PSA.133:1-3,PRO.17:7-8',
        167 => '1KI.15:25-34,1KI.16,1KI.17,ACT.10:24-48,PSA.134:1-3,PRO.17:9-11',
        168 => '1KI.18,ACT.11,PSA.135:1-21,PRO.17:12-13',
        169 => '1KI.19,ACT.12:1-23,PSA.136:1-26,PRO.17:14-15',
        170 => '1KI.20,1KI.21,ACT.12:24-25,ACT.13:1-15,PSA.137:1-9,PRO.17:16',
        171 => '1KI.22,ACT.13:16-41,PSA.138:1-8,PRO.17:17-18',
        172 => '2KI.1,2KI.2,ACT.13:42-52,ACT.14:1-7,PSA.139:1-24,PRO.17:19-21',
        173 => '2KI.3,2KI.4:1-17,ACT.14:8-28,PSA.140:1-13,PRO.17:22',
        174 => '2KI.4:18-44,2KI.5,ACT.15:1-35,PSA.141:1-10,PRO.17:23',
        175 => '2KI.6,2KI.7,ACT.15:36-41,ACT.16:1-15,PSA.142:1-7,PRO.17:24-25',
        176 => '2KI.8,2KI.9:1-13,ACT.16:16-40,PSA.143:1-12,PRO.17:26',
        177 => '2KI.9:14-37,2KI.10:1-31,ACT.17,PSA.144:1-15,PRO.17:27-28',
        178 => '2KI.10:32-36,2KI.11,2KI.12,ACT.18:1-22,PSA.145:1-21,PRO.18:1',
        179 => '2KI.13,2KI.14,ACT.18:23-28,ACT.19:1-12,PSA.146:1-10,PRO.18:2-3',
        180 => '2KI.15,2KI.16,ACT.19:13-41,PSA.147:1-20,PRO.18:4-5',
        181 => '2KI.17,2KI.18:1-12,ACT.20,PSA.148:1-14,PRO.18:6-7',
        182 => '2KI.18:13-37,2KI.19,ACT.21:1-17,PSA.149:1-9,PRO.18:8',
        183 => '2KI.20,2KI.21,2KI.22:1-2,ACT.21:18-36,PSA.150:1-6,PRO.18:9-10',
        184 => '2KI.22:3-20,2KI.23:1-30,ACT.21:37-40,ACT.22:1-16,PSA.1:1-6,PRO.18:11-12',
        185 => '2KI.23:31-37,2KI.24,2KI.25,ACT.22:17-30,ACT.23:1-10,PSA.2:1-12,PRO.18:13',
        186 => '1CH.1,1CH.2:1-17,ACT.23:11-35,PSA.3:1-8,PRO.18:14-15',
        187 => '1CH.2:18-55,1CH.3,1CH.4:1-4,ACT.24,PSA.4:1-8,PRO.18:16-18',
        188 => '1CH.4:5-43,1CH.5:1-17,ACT.25,PSA.5:1-12,PRO.18:19',
        189 => '1CH.5:18-26,1CH.6,ACT.26,PSA.6:1-10,PRO.18:20-21',
        190 => '1CH.7,1CH.8,ACT.27:1-20,PSA.7:1-17,PRO.18:22',
        191 => '1CH.9,1CH.10,ACT.27:21-44,PSA.8:1-9,PRO.18:23-24',
        192 => '1CH.11,1CH.12:1-18,ACT.28,PSA.9:1-12,PRO.19:1-3',
        193 => '1CH.12:19-40,1CH.13,1CH.14,ROM.1:1-17,PSA.9:13-20,PRO.19:4-5',
        194 => '1CH.15,1CH.16:1-36,ROM.1:18-32,PSA.10:1-15,PRO.19:6-7',
        195 => '1CH.16:37-43,1CH.17,1CH.18,ROM.2:1-24,PSA.10:16-18,PRO.19:8-9',
        196 => '1CH.19,1CH.20,1CH.21,ROM.2:25-29,ROM.3:1-8,PSA.11:1-7,PRO.19:10-12',
        197 => '1CH.22,1CH.23,ROM.3:9-31,PSA.12:1-8,PRO.19:13-14',
        198 => '1CH.24,1CH.25,1CH.26:1-11,ROM.4:1-12,PSA.13:1-6,PRO.19:15-16',
        199 => '1CH.26:12-32,1CH.27,ROM.4:13-25,ROM.5:1-5,PSA.14:1-7,PRO.19:17',
        200 => '1CH.28,1CH.29,ROM.5:6-21,PSA.15:1-5,PRO.19:18-19',
        201 => '2CH.1,2CH.2,2CH.3,ROM.6,PSA.16:1-11,PRO.19:20-21',
        202 => '2CH.4,2CH.5,2CH.6:1-11,ROM.7:1-13,PSA.17:1-15,PRO.19:22-23',
        203 => '2CH.6:12-42,2CH.7,2CH.8:1-10,ROM.7:14-25,ROM.8:1-8,PSA.18:1-15,PRO.19:24-25',
        204 => '2CH.8:11-18,2CH.9,2CH.10,ROM.8:9-25,PSA.18:16-36,PRO.19:26',
        205 => '2CH.11,2CH.12,2CH.13,ROM.8:26-39,PSA.18:37-50,PRO.19:27-29',
        206 => '2CH.14,2CH.15,2CH.16,ROM.9:1-24,PSA.19:1-14,PRO.20:1',
        207 => '2CH.17,2CH.18,ROM.9:25-33,ROM.10:1-13,PSA.20:1-9,PRO.20:2-3',
        208 => '2CH.19,2CH.20,ROM.10:14-21,ROM.11:1-12,PSA.21:1-13,PRO.20:4-6',
        209 => '2CH.21,2CH.22,2CH.23,ROM.11:13-36,PSA.22:1-18,PRO.20:7',
        210 => '2CH.24,2CH.25,ROM.12,PSA.22:19-31,PRO.20:8-10',
        211 => '2CH.26,2CH.27,2CH.28,ROM.13,PSA.23:1-6,PRO.20:11',
        212 => '2CH.29,ROM.14,PSA.24:1-10,PRO.20:12',
        213 => '2CH.30,2CH.31,ROM.15:1-22,PSA.25:1-15,PRO.20:13-15',
        214 => '2CH.32,2CH.33:1-13,ROM.15:23-33,ROM.16:1-9,PSA.25:16-22,PRO.20:16-18',
        215 => '2CH.33:14-25,2CH.34,ROM.16:10-27,PSA.26:1-12,PRO.20:19',
        216 => '2CH.35,2CH.36,1CO.1:1-17,PSA.27:1-6,PRO.20:20-21',
        217 => 'EZR.1,EZR.2,1CO.1:18-31,1CO.2:1-5,PSA.27:7-14,PRO.20:22-23',
        218 => 'EZR.3,EZR.4:1-23,1CO.2:6-16,1CO.3:1-4,PSA.28:1-9,PRO.20:24-25',
        219 => 'EZR.4:24,EZR.5,EZR.6,1CO.3:5-23,PSA.29:1-11,PRO.20:26-27',
        220 => 'EZR.7,EZR.8:1-20,1CO.4,PSA.30:1-12,PRO.20:28-30',
        221 => 'EZR.8:21-36,EZR.9,1CO.5,PSA.31:1-8,PRO.21:1-2',
        222 => 'EZR.10,1CO.6,PSA.31:9-18,PRO.21:3',
        223 => 'NEH.1,NEH.2,NEH.3:1-14,1CO.7:1-24,PSA.31:19-24,PRO.21:4',
        224 => 'NEH.3:15-32,NEH.4,NEH.5:1-13,1CO.7:25-40,PSA.32:1-11,PRO.21:5-7',
        225 => 'NEH.5:14-19,NEH.6,NEH.7:1-72,1CO.8,PSA.33:1-11,PRO.21:8-10',
        226 => 'NEH.7:73,NEH.8,NEH.9:1-21,1CO.9:1-18,PSA.33:12-22,PRO.21:11-12',
        227 => 'NEH.9:22-38,NEH.10,1CO.9:19-27,1CO.10:1-13,PSA.34:1-10,PRO.21:13',
        228 => 'NEH.11,NEH.12:1-26,1CO.10:14-33,PSA.34:11-22,PRO.21:14-16',
        229 => 'NEH.12:27-47,NEH.13,1CO.11:1-16,PSA.35:1-16,PRO.21:17-18',
        230 => 'EST.1,EST.2,EST.3,1CO.11:17-34,PSA.35:17-28,PRO.21:19-20',
        231 => 'EST.4,EST.5,EST.6,EST.7,1CO.12:1-26,PSA.36:1-12,PRO.21:21-22',
        232 => 'EST.8,EST.9,EST.10,1CO.12:27-31,1CO.13,PSA.37:1-11,PRO.21:23-24',
        233 => 'JOB.1,JOB.2,JOB.3,1CO.14:1-17,PSA.37:12-29,PRO.21:25-26',
        234 => 'JOB.4,JOB.5,JOB.6,JOB.7,1CO.14:18-40,PSA.37:30-40,PRO.21:27',
        235 => 'JOB.8,JOB.9,JOB.10,JOB.11,1CO.15:1-28,PSA.38:1-22,PRO.21:28-29',
        236 => 'JOB.12,JOB.13,JOB.14,JOB.15,1CO.15:29-58,PSA.39:1-13,PRO.21:30-31',
        237 => 'JOB.16,JOB.17,JOB.18,JOB.19,1CO.16,PSA.40:1-10,PRO.22:1',
        238 => 'JOB.20,JOB.21,JOB.22,2CO.1:1-11,PSA.40:11-17,PRO.22:2-4',
        239 => 'JOB.23,JOB.24,JOB.25,JOB.26,JOB.27,2CO.1:12-24,2CO.2:1-11,PSA.41:1-13,PRO.22:5-6',
        240 => 'JOB.28,JOB.29,JOB.30,2CO.2:12-17,PSA.42:1-11,PRO.22:7',
        241 => 'JOB.31,JOB.32,JOB.33,2CO.3,PSA.43:1-5,PRO.22:8-9',
        242 => 'JOB.34,JOB.35,JOB.36,2CO.4:1-12,PSA.44:1-8,PRO.22:10-12',
        243 => 'JOB.37,JOB.38,JOB.39,2CO.4:13-18,2CO.5:1-10,PSA.44:9-26,PRO.22:13',
        244 => 'JOB.40,JOB.41,JOB.42,2CO.5:11-21,PSA.45:1-17,PRO.22:14',
        245 => 'ECC.1,ECC.2,ECC.3,2CO.6:1-13,PSA.46:1-11,PRO.22:15',
        246 => 'ECC.4,ECC.5,ECC.6,2CO.6:14-18,2CO.7:1-7,PSA.47:1-9,PRO.22:16',
        247 => 'ECC.7,ECC.8,ECC.9,2CO.7:8-16,PSA.48:1-14,PRO.22:17-19',
        248 => 'ECC.10,ECC.11,ECC.12,2CO.8:1-15,PSA.49:1-20,PRO.22:20-21',
        249 => 'SNG.1,SNG.2,SNG.3,SNG.4,2CO.8:16-24,PSA.50:1-23,PRO.22:22-23',
        250 => 'SNG.5,SNG.6,SNG.7,SNG.8,2CO.9,PSA.51:1-19,PRO.22:24-25',
        251 => 'ISA.1,ISA.2,2CO.10,PSA.52:1-9,PRO.22:26-27',
        252 => 'ISA.3,ISA.4,ISA.5,2CO.11:1-15,PSA.53:1-6,PRO.22:28-29',
        253 => 'ISA.6,ISA.7,2CO.11:16-33,PSA.54:1-7,PRO.23:1-3',
        254 => 'ISA.8,ISA.9,2CO.12:1-10,PSA.55:1-23,PRO.23:4-5',
        255 => 'ISA.10,ISA.11,2CO.12:11-21,PSA.56:1-13,PRO.23:6-8',
        256 => 'ISA.12,ISA.13,ISA.14,2CO.13:1-13,PSA.57:1-11,PRO.23:9-11',
        257 => 'ISA.15,ISA.16,ISA.17,ISA.18,GAL.1,PSA.58:1-11,PRO.23:12',
        258 => 'ISA.19,ISA.20,ISA.21,GAL.2:1-16,PSA.59:1-17,PRO.23:13-14',
        259 => 'ISA.22,ISA.23,ISA.24,GAL.2:17-21,GAL.3:1-9,PSA.60:1-12,PRO.23:15-16',
        260 => 'ISA.25,ISA.26,ISA.27,ISA.28:1-13,GAL.3:10-22,PSA.61:1-8,PRO.23:17-18',
        261 => 'ISA.28:14-29,ISA.29,ISA.30:1-11,GAL.3:23-29,GAL.4,PSA.62:1-12,PRO.23:19-21',
        262 => 'ISA.30:12-33,ISA.31,ISA.32,ISA.33:1-9,GAL.5:1-12,PSA.63:1-11,PRO.23:22',
        263 => 'ISA.33:10-24,ISA.34,ISA.35,ISA.36,GAL.5:13-25,PSA.64:1-10,PRO.23:23',
        264 => 'ISA.37,ISA.38,GAL.6,PSA.65:1-13,PRO.23:24',
        265 => 'ISA.39,ISA.40,ISA.41:1-16,EPH.1,PSA.66:1-20,PRO.23:25-28',
        266 => 'ISA.41:17-29,ISA.42,ISA.43:1-13,EPH.2,PSA.67:1-7,PRO.23:29-35',
        267 => 'ISA.43:14-28,ISA.44,ISA.45:1-10,EPH.3,PSA.68:1-18,PRO.24:1-2',
        268 => 'ISA.45:11-25,ISA.46,ISA.47,ISA.48:1-11,EPH.4:1-16,PSA.68:19-35,PRO.24:3-4',
        269 => 'ISA.48:12-22,ISA.49,ISA.50,EPH.4:17-32,PSA.69:1-18,PRO.24:5-6',
        270 => 'ISA.51,ISA.52,ISA.53,EPH.5,PSA.69:19-36,PRO.24:7',
        271 => 'ISA.54,ISA.55,ISA.56,ISA.57:1-14,EPH.6,PSA.70:1-5,PRO.24:8',
        272 => 'ISA.57:15-21,ISA.58,ISA.59,PHP.1:1-26,PSA.71:1-24,PRO.24:9-10',
        273 => 'ISA.60,ISA.61,ISA.62:1-5,PHP.1:27-30,PHP.2:1-18,PSA.72:1-20,PRO.24:11-12',
        274 => 'ISA.62:6-12,ISA.63,ISA.64,ISA.65,PHP.2:19-30,PHP.3:1-3,PSA.73:1-28,PRO.24:13-14',
        275 => 'ISA.66,PHP.3:4-21,PSA.74:1-23,PRO.24:15-16',
        276 => 'JER.1,JER.2:1-30,PHP.4,PSA.75:1-10,PRO.24:17-20',
        277 => 'JER.2:31-37,JER.3,JER.4:1-18,COL.1:1-17,PSA.76:1-12,PRO.24:21-22',
        278 => 'JER.4:19-31,JER.5,JER.6:1-15,COL.1:18-29,COL.2:1-7,PSA.77:1-20,PRO.24:23-25',
        279 => 'JER.6:16-30,JER.7,JER.8:1-7,COL.2:8-23,PSA.78:1-31,PRO.24:26',
        280 => 'JER.8:8-22,JER.9,COL.3:1-17,PSA.78:32-55,PRO.24:27',
        281 => 'JER.10,JER.11,COL.3:18-25,COL.4,PSA.78:56-72,PRO.24:28-29',
        282 => 'JER.12,JER.13,JER.14:1-10,1TH.1,1TH.2:1-8,PSA.79:1-13,PRO.24:30-34',
        283 => 'JER.14:11-22,JER.15,JER.16:1-15,1TH.2:9-20,1TH.3,PSA.80:1-9,PRO.25:1-5',
        284 => 'JER.16:16-21,JER.17,JER.18,1TH.4,1TH.5:1-3,PSA.81:1-16,PRO.25:6-8',
        285 => 'JER.19,JER.20,JER.21,1TH.5:4-28,PSA.82:1-8,PRO.25:9-10',
        286 => 'JER.22,JER.23:1-20,2TH.1,PSA.83:1-18,PRO.25:11-14',
        287 => 'JER.23:21-40,JER.24,JER.25,2TH.2,PSA.84:1-12,PRO.25:15',
        288 => 'JER.26,JER.27,2TH.3,PSA.85:1-13,PRO.25:16',
        289 => 'JER.28,JER.29,1TI.1,PSA.86:1-17,PRO.25:17',
        290 => 'JER.30,JER.31:1-26,1TI.2,PSA.87:1-7,PRO.25:18-19',
        291 => 'JER.31:27-40,JER.32,1TI.3,PSA.88:1-18,PRO.25:20-22',
        292 => 'JER.33,JER.34,1TI.4,PSA.89:1-13,PRO.25:23-24',
        293 => 'JER.35,JER.36,1TI.5,PSA.89:14-37,PRO.25:25-27',
        294 => 'JER.37,JER.38,1TI.6,PSA.89:38-52,PRO.25:28',
        295 => 'JER.39,JER.40,JER.41,2TI.1,PSA.90,PSA.91:1-16,PRO.26:1-2',
        296 => 'JER.42,JER.43,JER.44:1-23,2TI.2:1-21,PSA.92,PSA.93:1-5,PRO.26:3-5',
        297 => 'JER.44:24-30,JER.45,JER.46,JER.47,2TI.2:22-26,2TI.3,PSA.94:1-23,PRO.26:6-8',
        298 => 'JER.48,JER.49:1-22,2TI.4,PSA.95,PSA.96:1-13,PRO.26:9-12',
        299 => 'JER.49:23-39,JER.50,TIT.1,PSA.97,PSA.98:1-9,PRO.26:13-16',
        300 => 'JER.51:1-53,TIT.2,PSA.99:1-9,PRO.26:17',
        301 => 'JER.51:54-64,JER.52,TIT.3,PSA.100:1-5,PRO.26:18-19',
        302 => 'LAM.1,LAM.2,PHM.1,PSA.101:1-8,PRO.26:20',
        303 => 'LAM.3,HEB.1,PSA.102:1-28,PRO.26:21-22',
        304 => 'LAM.4,LAM.5,HEB.2,PSA.103:1-22,PRO.26:23',
        305 => 'EZK.1,EZK.2,EZK.3:1-15,HEB.3,PSA.104:1-23,PRO.26:24-26',
        306 => 'EZK.3:16-27,EZK.4,EZK.5,EZK.6,HEB.4,PSA.104:24-35,PRO.26:27',
        307 => 'EZK.7,EZK.8,EZK.9,HEB.5,PSA.105:1-15,PRO.26:28',
        308 => 'EZK.10,EZK.11,HEB.6,PSA.105:16-36,PRO.27:1-2',
        309 => 'EZK.12,EZK.13,EZK.14:1-11,HEB.7:1-17,PSA.105:37-45,PRO.27:3',
        310 => 'EZK.14:12-23,EZK.15,EZK.16:1-41,HEB.7:18-28,PSA.106:1-12,PRO.27:4-6',
        311 => 'EZK.16:42-63,EZK.17,HEB.8,PSA.106:13-31,PRO.27:7-9',
        312 => 'EZK.18,EZK.19,HEB.9:1-10,PSA.106:32-48,PRO.27:10',
        313 => 'EZK.20,HEB.9:11-28,PSA.107:1-43,PRO.27:11',
        314 => 'EZK.21,EZK.22,HEB.10:1-17,PSA.108:1-13,PRO.27:12',
        315 => 'EZK.23,HEB.10:18-39,PSA.109:1-31,PRO.27:13',
        316 => 'EZK.24,EZK.25,EZK.26,HEB.11:1-16,PSA.110:1-7,PRO.27:14',
        317 => 'EZK.27,EZK.28,HEB.11:17-31,PSA.111:1-10,PRO.27:15-16',
        318 => 'EZK.29,EZK.30,HEB.11:32-40,HEB.12:1-13,PSA.112:1-10,PRO.27:17',
        319 => 'EZK.31,EZK.32,HEB.12:14-29,PSA.113,PSA.114,PRO.27:18-20',
        320 => 'EZK.33,EZK.34,HEB.13,PSA.115:1-18,PRO.27:21-22',
        321 => 'EZK.35,EZK.36,JAS.1:1-18,PSA.116:1-19,PRO.27:23-27',
        322 => 'EZK.37,EZK.38,JAS.1:19-27,JAS.2:1-17,PSA.117:1-2,PRO.28:1',
        323 => 'EZK.39,EZK.40:1-27,JAS.2:18-26,JAS.3,PSA.118:1-18,PRO.28:2',
        324 => 'EZK.40:28-49,EZK.41,JAS.4,PSA.118:19-29,PRO.28:3-5',
        325 => 'EZK.42,EZK.43,JAS.5,PSA.119:1-16,PRO.28:6-7',
        326 => 'EZK.44,EZK.45:1-12,1PE.1:1-12,PSA.119:17-32,PRO.28:8-10',
        327 => 'EZK.45:13-25,EZK.46,1PE.1:13-25,1PE.2:1-10,PSA.119:33-48,PRO.28:11',
        328 => 'EZK.47,EZK.48,1PE.2:11-25,1PE.3:1-7,PSA.119:49-64,PRO.28:12-13',
        329 => 'DAN.1,DAN.2:1-23,1PE.3:8-22,1PE.4:1-6,PSA.119:65-80,PRO.28:14',
        330 => 'DAN.2:24-49,DAN.3,1PE.4:7-19,1PE.5,PSA.119:81-96,PRO.28:15-16',
        331 => 'DAN.4,2PE.1,PSA.119:97-112,PRO.28:17-18',
        332 => 'DAN.5,2PE.2,PSA.119:113-128,PRO.28:19-20',
        333 => 'DAN.6,2PE.3,PSA.119:129-152,PRO.28:21-22',
        334 => 'DAN.7,1JN.1,PSA.119:153-176,PRO.28:23-24',
        335 => 'DAN.8,1JN.2:1-17,PSA.120:1-7,PRO.28:25-26',
        336 => 'DAN.9,DAN.10,DAN.11:1,1JN.2:18-29,1JN.3:1-6,PSA.121:1-8,PRO.28:27-28',
        337 => 'DAN.11:2-35,1JN.3:7-24,PSA.122:1-9,PRO.29:1',
        338 => 'DAN.11:36-45,DAN.12,1JN.4,PSA.123:1-4,PRO.29:2-4',
        339 => 'HOS.1,HOS.2,HOS.3,1JN.5,PSA.124:1-8,PRO.29:5-8',
        340 => 'HOS.4,HOS.5,2JN.1,PSA.125:1-5,PRO.29:9-11',
        341 => 'HOS.6,HOS.7,HOS.8,HOS.9,3JN.1,PSA.126:1-6,PRO.29:12-14',
        342 => 'HOS.10,HOS.11,HOS.12,HOS.13,HOS.14,JUD.1,PSA.127:1-5,PRO.29:15-17',
        343 => 'JOL.1,JOL.2,JOL.3,REV.1,PSA.128:1-6,PRO.29:18',
        344 => 'AMO.1,AMO.2,AMO.3,REV.2:1-17,PSA.129:1-8,PRO.29:19-20',
        345 => 'AMO.4,AMO.5,AMO.6,REV.2:18-29,REV.3:1-6,PSA.130:1-8,PRO.29:21-22',
        346 => 'AMO.7,AMO.8,AMO.9,REV.3:7-22,PSA.131:1-3,PRO.29:23',
        347 => 'OBA.1,REV.4,PSA.132:1-18,PRO.29:24-25',
        348 => 'JON.1,JON.2,JON.3,JON.4,REV.5,PSA.133:1-3,PRO.29:26-27',
        349 => 'MIC.1,MIC.2,MIC.3,MIC.4,REV.6,PSA.134:1-3,PRO.30:1-4',
        350 => 'MIC.5,MIC.6,MIC.7,REV.7,PSA.135:1-21,PRO.30:5-6',
        351 => 'NAM.1,NAM.2,NAM.3,REV.8,PSA.136:1-26,PRO.30:7-9',
        352 => 'HAB.1,HAB.2,HAB.3,REV.9,PSA.137:1-9,PRO.30:10',
        353 => 'ZEP.1,ZEP.2,ZEP.3,REV.10,PSA.138:1-8,PRO.30:11-14',
        354 => 'HAG.1,HAG.2,REV.11,PSA.139:1-24,PRO.30:15-16',
        355 => 'ZEC.1,REV.12,PSA.140:1-13,PRO.30:17',
        356 => 'ZEC.2,ZEC.3,REV.13,PSA.141:1-10,PRO.30:18-20',
        357 => 'ZEC.4,ZEC.5,REV.14,PSA.142:1-7,PRO.30:21-23',
        358 => 'ZEC.6,ZEC.7,REV.15,PSA.143:1-12,PRO.30:24-28',
        359 => 'ZEC.8,REV.16,PSA.144:1-15,PRO.30:29-31',
        360 => 'ZEC.9,REV.17,PSA.145:1-21,PRO.30:32',
        361 => 'ZEC.10,ZEC.11,REV.18,PSA.146:1-10,PRO.30:33',
        362 => 'ZEC.12,ZEC.13,REV.19,PSA.147:1-20,PRO.31:1-7',
        363 => 'ZEC.14,REV.20,PSA.148:1-4,PRO.31:8-9',
        364 => 'MAL.1,MAL.2,REV.21,PSA.149:1-9,PRO.31:10-24',
        365 => 'MAL.3,MAL.4,REV.22,PSA.150:1-6,PRO.31:25-31',
    ];
}
