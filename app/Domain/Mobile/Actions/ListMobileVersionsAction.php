<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Actions;

use App\Domain\Mobile\Models\MobileVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListMobileVersionsAction
{
    /**
     * @return LengthAwarePaginator<int, MobileVersion>
     */
    public function handle(?string $platform, int $page, int $perPage): LengthAwarePaginator
    {
        $query = MobileVersion::query()
            ->orderBy('platform')
            ->orderBy('kind');

        if ($platform !== null && $platform !== '') {
            $query->forPlatform($platform);
        }

        return $query->paginate($perPage, page: $page);
    }
}
