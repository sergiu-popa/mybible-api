<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\UpdateResourceBookAction;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\Admin\EducationalResources\UpdateResourceBookRequest;
use App\Http\Resources\EducationalResources\AdminResourceBookResource;

final class UpdateResourceBookController
{
    public function __invoke(
        UpdateResourceBookRequest $request,
        ResourceBook $book,
        UpdateResourceBookAction $action,
    ): AdminResourceBookResource {
        return AdminResourceBookResource::make($action->execute($book, $request->changes()));
    }
}
