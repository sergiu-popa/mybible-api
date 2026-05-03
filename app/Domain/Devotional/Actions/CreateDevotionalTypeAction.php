<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\DataTransferObjects\CreateDevotionalTypeData;
use App\Domain\Devotional\Models\DevotionalType;

final class CreateDevotionalTypeAction
{
    public function handle(CreateDevotionalTypeData $data): DevotionalType
    {
        return DevotionalType::query()->create([
            'slug' => $data->slug,
            'title' => $data->title,
            'position' => $data->position,
            'language' => $data->language,
        ]);
    }
}
