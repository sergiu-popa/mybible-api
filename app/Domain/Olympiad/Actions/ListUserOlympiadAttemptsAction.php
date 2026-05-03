<?php

declare(strict_types=1);

namespace App\Domain\Olympiad\Actions;

use App\Domain\Olympiad\DataTransferObjects\ListOlympiadAttemptsFilter;
use App\Domain\Olympiad\Models\OlympiadAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListUserOlympiadAttemptsAction
{
    /**
     * @return LengthAwarePaginator<int, OlympiadAttempt>
     */
    public function handle(User $user, ListOlympiadAttemptsFilter $filter, int $page, int $perPage): LengthAwarePaginator
    {
        return OlympiadAttempt::query()
            ->forUser($user->id)
            ->forFilters($filter->language, $filter->book, $filter->chaptersLabel)
            ->newestFirst()
            ->paginate($perPage, page: $page);
    }
}
