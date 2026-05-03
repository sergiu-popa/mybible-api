<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Actions;

use App\Domain\QrCode\Models\QrCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListAdminQrCodesAction
{
    /**
     * @return LengthAwarePaginator<int, QrCode>
     */
    public function handle(int $page, int $perPage): LengthAwarePaginator
    {
        return QrCode::query()
            ->orderBy('place')
            ->orderBy('source')
            ->paginate($perPage, page: $page);
    }
}
