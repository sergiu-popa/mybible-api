<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Actions;

use App\Domain\Devotional\Models\Devotional;
use App\Domain\Devotional\Models\DevotionalType;
use Illuminate\Validation\ValidationException;

final class DeleteDevotionalTypeAction
{
    public function handle(DevotionalType $type): void
    {
        $hasDevotionals = Devotional::query()
            ->where('type_id', $type->id)
            ->exists();

        if ($hasDevotionals) {
            throw ValidationException::withMessages([
                'type' => ['Cannot delete a devotional type that still has devotionals.'],
            ]);
        }

        $type->delete();
    }
}
