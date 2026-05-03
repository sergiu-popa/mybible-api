<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\ListOlympiadAttemptsFilter;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAdminOlympiadAttemptsAction
{
    /**
     * @return LengthAwarePaginator<int, OlympiadAttempt>
     */
    public function handle(ListOlympiadAttemptsFilter $filter, int $page, int $perPage): LengthAwarePaginator
    {
        $query = OlympiadAttempt::query()
            ->forFilters($filter->language, $filter->book, $filter->chaptersLabel)
            ->newestFirst();

        if ($filter->userId !== null) {
            $query->where('user_id', $filter->userId);
        }

        return $query->paginate($perPage, page: $page);
    }
}
