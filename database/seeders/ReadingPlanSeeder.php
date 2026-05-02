<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\ReadingPlans\Enums\FragmentType;
use App\Domain\ReadingPlans\Enums\ReadingPlanStatus;
use App\Domain\ReadingPlans\Models\ReadingPlan;
use App\Domain\ReadingPlans\Models\ReadingPlanDay;
use App\Domain\ReadingPlans\Models\ReadingPlanDayFragment;
use Illuminate\Database\Seeder;

final class ReadingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = ReadingPlan::query()->create([
            'slug' => 'seven-days-of-wisdom',
            'name' => [
                'en' => 'Seven Days of Wisdom',
                'ro' => 'Șapte zile de înțelepciune',
                'hu' => 'Hét nap bölcsesség',
                'es' => 'Siete días de sabiduría',
                'fr' => 'Sept jours de sagesse',
                'de' => 'Sieben Tage der Weisheit',
                'it' => 'Sette giorni di saggezza',
            ],
            'description' => [
                'en' => 'A week-long plan walking through Proverbs.',
                'ro' => 'Un plan de o săptămână prin cartea Proverbelor.',
                'hu' => 'Egyhetes terv a Példabeszédek könyvén keresztül.',
                'es' => 'Un plan de una semana a través del libro de Proverbios.',
                'fr' => 'Un plan d\'une semaine à travers le livre des Proverbes.',
                'de' => 'Ein einwöchiger Plan durch das Buch der Sprüche.',
                'it' => 'Un piano di una settimana attraverso il libro dei Proverbi.',
            ],
            'image' => [
                'en' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-en.jpg',
                'ro' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-ro.jpg',
                'hu' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-hu.jpg',
                'es' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-es.jpg',
                'fr' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-fr.jpg',
                'de' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-de.jpg',
                'it' => 'https://cdn.example.com/plans/seven-days-wisdom/cover-it.jpg',
            ],
            'thumbnail' => [
                'en' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-en.jpg',
                'ro' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-ro.jpg',
                'hu' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-hu.jpg',
                'es' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-es.jpg',
                'fr' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-fr.jpg',
                'de' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-de.jpg',
                'it' => 'https://cdn.example.com/plans/seven-days-wisdom/thumb-it.jpg',
            ],
            'status' => ReadingPlanStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        foreach (range(1, 7) as $position) {
            $day = ReadingPlanDay::query()->create([
                'reading_plan_id' => $plan->id,
                'position' => $position,
            ]);

            ReadingPlanDayFragment::query()->create([
                'reading_plan_day_id' => $day->id,
                'position' => 1,
                'type' => FragmentType::Html,
                'content' => [
                    'en' => "<p>Day {$position} introduction.</p>",
                    'ro' => "<p>Introducere ziua {$position}.</p>",
                    'hu' => "<p>{$position}. nap bevezetője.</p>",
                    'es' => "<p>Introducción del día {$position}.</p>",
                    'fr' => "<p>Introduction du jour {$position}.</p>",
                    'de' => "<p>Einführung Tag {$position}.</p>",
                    'it' => "<p>Introduzione del giorno {$position}.</p>",
                ],
            ]);

            ReadingPlanDayFragment::query()->create([
                'reading_plan_day_id' => $day->id,
                'position' => 2,
                'type' => FragmentType::References,
                'content' => ["PRO.{$position}"],
            ]);
        }
    }
}
