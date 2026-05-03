<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class DeleteTrimesterAction
{
    public function execute(SabbathSchoolTrimester $trimester): void
    {
        $id = $trimester->id;
        $trimester->delete();

        Cache::tags(SabbathSchoolCacheKeys::tagsForTrimester($id))->flush();
        Cache::tags(SabbathSchoolCacheKeys::tagsForTrimestersList())->flush();
    }
}
