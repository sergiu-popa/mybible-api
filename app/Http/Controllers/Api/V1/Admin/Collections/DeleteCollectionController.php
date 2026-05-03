<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Collections;

use App\Domain\Collections\Actions\DeleteCollectionAction;
use App\Domain\Collections\Models\Collection;
use App\Http\Requests\Admin\Collections\DeleteCollectionRequest;
use Illuminate\Http\Response;

final class DeleteCollectionController
{
    public function __invoke(
        DeleteCollectionRequest $request,
        Collection $collection,
        DeleteCollectionAction $action,
    ): Response {
        $action->handle($collection);

        return response()->noContent();
    }
}
