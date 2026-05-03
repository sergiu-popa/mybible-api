<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\EducationalResources;

use App\Domain\Analytics\Actions\RecordResourceDownloadAction;
use App\Domain\Analytics\Support\ClientContextResolver;
use App\Domain\EducationalResources\Models\ResourceBook;
use App\Domain\EducationalResources\Models\ResourceBookChapter;
use App\Http\Requests\EducationalResources\RecordResourceBookChapterDownloadRequest;
use Illuminate\Http\Response;

final class RecordResourceBookChapterDownloadController
{
    public function __invoke(
        RecordResourceBookChapterDownloadRequest $request,
        ResourceBook $book,
        ResourceBookChapter $chapter,
        RecordResourceDownloadAction $action,
    ): Response {
        $context = ClientContextResolver::fromRequest($request);

        $action->execute($chapter, $context, 'resource_book.chapter.downloaded');

        return response()->noContent();
    }
}
