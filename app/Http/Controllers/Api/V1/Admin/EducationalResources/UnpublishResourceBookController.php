<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\SetResourceBookPublicationAction;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\Admin\EducationalResources\UnpublishResourceBookRequest;
use App\Http\Resources\EducationalResources\AdminResourceBookResource;

final class UnpublishResourceBookController
{
    public function __invoke(
        UnpublishResourceBookRequest $request,
        ResourceBook $book,
        SetResourceBookPublicationAction $action,
    ): AdminResourceBookResource {
        return AdminResourceBookResource::make($action->execute($book, false));
    }
}
