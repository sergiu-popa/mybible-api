<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\DataTransferObjects\UpdateDevotionalTypeData;
use App\Domain\Devotional\Models\DevotionalType;

final class UpdateDevotionalTypeAction
{
    public function handle(DevotionalType $type, UpdateDevotionalTypeData $data): DevotionalType
    {
        $attributes = [];

        if ($data->slugProvided && $data->slug !== null) {
            $attributes['slug'] = $data->slug;
        }
        if ($data->titleProvided && $data->title !== null) {
            $attributes['title'] = $data->title;
        }
        if ($data->positionProvided && $data->position !== null) {
            $attributes['position'] = $data->position;
        }
        if ($data->languageProvided) {
            $attributes['language'] = $data->language;
        }

        if ($attributes !== []) {
            $type->update($attributes);
        }

        return $type->fresh() ?? $type;
    }
}
