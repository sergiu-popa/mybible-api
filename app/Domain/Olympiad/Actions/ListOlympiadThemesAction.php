<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\OlympiadThemeFilter;
use App\Domain\Olympiad\Models\OlympiadQuestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListOlympiadThemesAction
{
    public function execute(OlympiadThemeFilter $filter): LengthAwarePaginator
    {
        return OlympiadQuestion::query()
            ->forLanguage($filter->language)
            ->themes()
            ->paginate($filter->perPage);
    }
}
