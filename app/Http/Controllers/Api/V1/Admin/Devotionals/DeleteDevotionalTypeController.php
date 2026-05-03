<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Devotionals;

use App\Domain\Devotional\Actions\DeleteDevotionalTypeAction;
use App\Domain\Devotional\Models\DevotionalType;
use App\Http\Requests\Admin\Devotionals\DeleteDevotionalTypeRequest;
use Illuminate\Http\Response;

final class DeleteDevotionalTypeController
{
    public function __invoke(
        DeleteDevotionalTypeRequest $request,
        DevotionalType $type,
        DeleteDevotionalTypeAction $action,
    ): Response {
        $action->handle($type);

        return response()->noContent();
    }
}
