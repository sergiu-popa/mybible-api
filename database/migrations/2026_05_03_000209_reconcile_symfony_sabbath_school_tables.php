<?php

declare(strict_types=1);

use App\Domain\Migration\Support\ReconcileTableHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The `(language, age_group, trimester_id, date_from, date_to)` UNIQUE on
 * `sabbath_school_lessons` is owned by MBA-025 (the story that
 * introduces `trimester_id` and `age_group`). Adding it here would be
 * premature.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = [
            'sb_trimester' => 'sabbath_school_trimesters',
            'sb_lesson' => 'sabbath_school_lessons',
            'sb_section' => 'sabbath_school_segments',
            'sb_content' => 'sabbath_school_segment_contents',
            'sb_answer' => 'sabbath_school_answers',
            'sb_favorite' => 'sabbath_school_favorites',
            'sb_highlight' => 'sabbath_school_highlights',
        ];

        $any = false;

        foreach (array_keys($legacy) as $name) {
            if (Schema::hasTable($name)) {
                $any = true;
                break;
            }
        }

        if (! $any) {
            return;
        }

        foreach ($legacy as $from => $to) {
            ReconcileTableHelper::rename($from, $to);
        }
    }

    public function down(): void
    {
        $reverse = [
            'sabbath_school_highlights' => 'sb_highlight',
            'sabbath_school_favorites' => 'sb_favorite',
            'sabbath_school_answers' => 'sb_answer',
            'sabbath_school_segment_contents' => 'sb_content',
            'sabbath_school_segments' => 'sb_section',
            'sabbath_school_lessons' => 'sb_lesson',
            'sabbath_school_trimesters' => 'sb_trimester',
        ];

        foreach ($reverse as $from => $to) {
            ReconcileTableHelper::rename($from, $to);
        }
    }
};
