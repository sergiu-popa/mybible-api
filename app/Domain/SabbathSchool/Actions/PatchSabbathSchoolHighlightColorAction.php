<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolHighlight;

final class PatchSabbathSchoolHighlightColorAction
{
    public function execute(SabbathSchoolHighlight $highlight, string $color): SabbathSchoolHighlight
    {
        $highlight->color = $color;
        $highlight->save();

        return $highlight->refresh();
    }
}
