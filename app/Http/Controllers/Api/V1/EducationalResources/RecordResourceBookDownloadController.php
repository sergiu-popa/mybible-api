<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\Analytics\Actions\RecordResourceDownloadAction;
use App\Domain\Analytics\Support\ClientContextResolver;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\EducationalResources\RecordResourceBookDownloadRequest;
use Illuminate\Http\Response;

final class RecordResourceBookDownloadController
{
    public function __invoke(
        RecordResourceBookDownloadRequest $request,
        ResourceBook $book,
        RecordResourceDownloadAction $action,
    ): Response {
        $context = ClientContextResolver::fromRequest($request);

        $action->execute($book, $context, 'resource_book.downloaded');

        return response()->noContent();
    }
}
