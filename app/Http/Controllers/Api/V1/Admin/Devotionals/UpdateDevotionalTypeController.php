<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Devotionals;

use App\Domain\Devotional\Actions\UpdateDevotionalTypeAction;
use App\Domain\Devotional\Models\DevotionalType;
use App\Http\Requests\Admin\Devotionals\UpdateDevotionalTypeRequest;
use App\Http\Resources\Devotionals\DevotionalTypeResource;

final class UpdateDevotionalTypeController
{
    public function __invoke(
        UpdateDevotionalTypeRequest $request,
        DevotionalType $type,
        UpdateDevotionalTypeAction $action,
    ): DevotionalTypeResource {
        return DevotionalTypeResource::make($action->handle($type, $request->toData()));
    }
}
