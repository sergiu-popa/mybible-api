<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\DataTransferObjects\FetchDevotionalData;
use App\Domain\Devotional\Models\Devotional;

final class FetchDevotionalAction
{
    public function execute(FetchDevotionalData $data): Devotional
    {
        return Devotional::query()
            ->forLanguage($data->language)
            ->ofType($data->type)
            ->onDate($data->date)
            ->firstOrFail();
    }
}
