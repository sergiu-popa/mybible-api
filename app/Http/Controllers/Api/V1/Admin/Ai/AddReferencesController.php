<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Ai;

use App\Domain\AI\Actions\AddReferencesAction;
use App\Http\Requests\Admin\Ai\AddReferencesRequest;
use App\Http\Resources\AI\AddReferencesResource;

final class AddReferencesController
{
    public function __invoke(
        AddReferencesRequest $request,
        AddReferencesAction $action,
    ): AddReferencesResource {
        return AddReferencesResource::make($action->execute($request->toData()));
    }
}
