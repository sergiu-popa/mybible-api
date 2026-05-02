<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use Database\Seeders\Concerns\ParsesPlanReferences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the "Read the Bible in 120 Days" plan, 120 daily readings.
 *
 * Idempotent: re-running updates plan metadata, deletes existing days and
 * fragments via cascade, and rebuilds them.
 */
final class OneHundredTwentyDayBibleReadingPlanSeeder extends Seeder
{
    use ParsesPlanReferences;

    private const SLUG = '120-day-bible-reading-plan';

    public function run(): void
    {
        DB::transaction(function (): void {
            $plan = ReadingPlan::query()->updateOrCreate(
                ['slug' => self::SLUG],
                [
                    'name' => [
                        'en' => 'Read the Bible in 120 Days',
                        'ro' => 'Citește Biblia în 120 de zile',
                        'hu' => 'Olvasd a Bibliát 120 nap alatt',
                        'es' => 'Lee la Biblia en 120 días',
                        'fr' => 'Lisez la Bible en 120 jours',
                        'de' => 'Lies die Bibel in 120 Tagen',
                        'it' => 'Leggi la Bibbia in 120 giorni',
                    ],
                    'description' => [
                        'en' => 'Read through the entire Bible in 120 days, with passages divided into four 30-day sections covering the whole canon.',
                        'ro' => 'Citește întreaga Biblie în 120 de zile, cu pasaje împărțite în patru secțiuni de 30 de zile care acoperă tot canonul.',
                        'hu' => 'Olvasd végig az egész Bibliát 120 nap alatt; a szakaszok négy 30 napos részre vannak osztva, amelyek lefedik az egész kánont.',
                        'es' => 'Lee toda la Biblia en 120 días, con pasajes divididos en cuatro secciones de 30 días que abarcan todo el canon.',
                        'fr' => 'Lisez la Bible entière en 120 jours, avec des passages répartis en quatre sections de 30 jours couvrant l\'ensemble du canon.',
                        'de' => 'Lies die ganze Bibel in 120 Tagen, mit Abschnitten in vier 30-Tage-Blöcken, die den gesamten Kanon abdecken.',
                        'it' => 'Leggi tutta la Bibbia in 120 giorni, con brani suddivisi in quattro sezioni di 30 giorni che coprono l\'intero canone.',
                    ],
                    'image' => [
                        'en' => 'https://placehold.co/1200x630?text=120-Day+Bible+Plan',
                        'ro' => 'https://placehold.co/1200x630?text=Plan+120+zile',
                        'hu' => 'https://placehold.co/1200x630?text=120+napos+terv',
                        'es' => 'https://placehold.co/1200x630?text=Plan+120+dias',
                        'fr' => 'https://placehold.co/1200x630?text=Plan+120+jours',
                        'de' => 'https://placehold.co/1200x630?text=120-Tage-Plan',
                        'it' => 'https://placehold.co/1200x630?text=Piano+120+giorni',
                    ],
                    'thumbnail' => [
                        'en' => 'https://placehold.co/400x400?text=120+Days',
                        'ro' => 'https://placehold.co/400x400?text=120+zile',
                        'hu' => 'https://placehold.co/400x400?text=120+nap',
                        'es' => 'https://placehold.co/400x400?text=120+dias',
                        'fr' => 'https://placehold.co/400x400?text=120+jours',
                        'de' => 'https://placehold.co/400x400?text=120+Tage',
                        'it' => 'https://placehold.co/400x400?text=120+giorni',
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

                $this->seedReferenceFragments($day, $references);
            }
        });
    }

    /**
     * @var array<int, string>
     */
    private const DAYS = [
        1 => 'GEN.1-9',
        2 => 'GEN.10-19',
        3 => 'GEN.20-26',
        4 => 'GEN.27-34',
        5 => 'GEN.35-42',
        6 => 'GEN.43-50',
        7 => 'EXO.1-7',
        8 => 'EXO.8-15',
        9 => 'EXO.16-24',
        10 => 'EXO.25-32',
        11 => 'EXO.33-40',
        12 => 'LEV.1-6',
        13 => 'LEV.7-13',
        14 => 'LEV.14-21',
        15 => 'LEV.22-27',
        16 => 'NUM.1-6',
        17 => 'NUM.7-13',
        18 => 'NUM.14-20',
        19 => 'NUM.21-28',
        20 => 'NUM.29-36',
        21 => 'DEU.1-7',
        22 => 'DEU.8-16',
        23 => 'DEU.17-27',
        24 => 'DEU.28-34',
        25 => 'JOS.1-8',
        26 => 'JOS.9-16',
        27 => 'JOS.17-24',
        28 => 'JDG.1-8',
        29 => 'JDG.9-18',
        30 => 'JDG.19-21,RUT.1-4',
        31 => '1SA.1-11',
        32 => '1SA.12-21',
        33 => '1SA.22-31',
        34 => '2SA.1-11',
        35 => '2SA.12-18',
        36 => '2SA.19-24',
        37 => '1KI.1-7',
        38 => '1KI.8-13',
        39 => '1KI.14-22',
        40 => '2KI.1-7',
        41 => '2KI.8-15',
        42 => '2KI.16-25',
        43 => '1CH.1-9',
        44 => '1CH.10-18',
        45 => '1CH.19-29',
        46 => '2CH.1-9',
        47 => '2CH.10-21',
        48 => '2CH.22-29',
        49 => '2CH.30-36',
        50 => 'EZR.1-10',
        51 => 'NEH.1-8',
        52 => 'NEH.9-13',
        53 => 'EST.1-10',
        54 => 'JOB.1-15',
        55 => 'JOB.16-31',
        56 => 'JOB.32-42',
        57 => 'PSA.1-24',
        58 => 'PSA.25-41',
        59 => 'PSA.42-65',
        60 => 'PSA.66-87',
        61 => 'PSA.88-119',
        62 => 'PSA.120-150',
        63 => 'PRO.1-14',
        64 => 'PRO.15-26',
        65 => 'PRO.27-31',
        66 => 'ECC.1-12,SNG.1-8',
        67 => 'ISA.1-13',
        68 => 'ISA.14-27',
        69 => 'ISA.28-39',
        70 => 'ISA.40-49',
        71 => 'ISA.50-66',
        72 => 'JER.1-8',
        73 => 'JER.9-18',
        74 => 'JER.19-27',
        75 => 'JER.28-36',
        76 => 'JER.37-46',
        77 => 'JER.47-52',
        78 => 'LAM.1-5,EZK.1-4',
        79 => 'EZK.5-15',
        80 => 'EZK.16-23',
        81 => 'EZK.24-33',
        82 => 'EZK.34-41',
        83 => 'EZK.42-48',
        84 => 'DAN.1-6',
        85 => 'DAN.7-12',
        86 => 'HOS.1-14',
        87 => 'JOL.1-3,AMO.1-9',
        88 => 'OBA.1,MIC.1-7',
        89 => 'NAM.1-3,HAG.1-2',
        90 => 'ZEC.1-14,MAL.1-4',
        91 => 'MAT.1-9',
        92 => 'MAT.10-15',
        93 => 'MAT.16-22',
        94 => 'MAT.23-28',
        95 => 'MRK.1-8',
        96 => 'MRK.9-16',
        97 => 'LUK.1-6',
        98 => 'LUK.7-11',
        99 => 'LUK.12-18',
        100 => 'LUK.19-24',
        101 => 'JHN.1-7',
        102 => 'JHN.8-13',
        103 => 'JHN.14-21',
        104 => 'ACT.1-7',
        105 => 'ACT.8-14',
        106 => 'ACT.15-21',
        107 => 'ACT.22-28',
        108 => 'ROM.1-8',
        109 => 'ROM.9-16',
        110 => '1CO.1-9',
        111 => '1CO.10-16',
        112 => '2CO.1-13',
        113 => 'GAL.1-6,EPH.1-6',
        114 => 'PHP.1-4,COL.1-4,1TH.1-5,2TH.1-3',
        115 => '1TI.1-6,2TI.1-4,TIT.1-3,PHM.1',
        116 => 'HEB.1-13',
        117 => 'JAS.1-5,1PE.1-5,2PE.1-3',
        118 => '1JN.1-5,2JN.1,3JN.1,JUD.1',
        119 => 'REV.1-11',
        120 => 'REV.12-22',
    ];
}
