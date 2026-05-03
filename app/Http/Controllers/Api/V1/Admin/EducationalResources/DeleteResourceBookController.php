<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Actions\DeleteResourceBookAction;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\Admin\EducationalResources\DeleteResourceBookRequest;
use Illuminate\Http\Response;

final class DeleteResourceBookController
{
    public function __invoke(
        DeleteResourceBookRequest $request,
        ResourceBook $book,
        DeleteResourceBookAction $action,
    ): Response {
        $action->execute($book);

        return response()->noContent();
    }
}
