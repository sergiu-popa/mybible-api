<?php

declare(strict_types=1);

namespace App\Domain\SabbathSchool\Actions;

use App\Domain\SabbathSchool\DataTransferObjects\TrimesterData;
use App\Domain\SabbathSchool\Models\SabbathSchoolTrimester;
use App\Domain\SabbathSchool\Support\SabbathSchoolCacheKeys;
use Illuminate\Support\Facades\Cache;

final class CreateTrimesterAction
{
    public function execute(TrimesterData $data): SabbathSchoolTrimester
    {
        $trimester = SabbathSchoolTrimester::create($data->toArray());

        Cache::tags(SabbathSchoolCacheKeys::tagsForTrimestersList())->flush();

        return $trimester;
    }
}
