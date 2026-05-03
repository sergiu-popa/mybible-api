<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\UpdateTrimesterData;
use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class UpdateTrimesterAction
{
    public function execute(SabbathSchoolTrimester $trimester, UpdateTrimesterData $data): SabbathSchoolTrimester
    {
        $trimester->fill($data->toArray())->save();

        Cache::tags(SabbathSchoolCacheKeys::tagsForTrimester($trimester->id))->flush();
        Cache::tags(SabbathSchoolCacheKeys::tagsForTrimestersList())->flush();

        return $trimester;
    }
}
