<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\EducationalResources;

use App\Domain\EducationalResources\Models\ResourceBook;
use App\Http\Requests\Admin\EducationalResources\ListResourceBookChaptersRequest;
use App\Http\Resources\EducationalResources\AdminResourceBookChapterResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListResourceBookChaptersController
{
    public function __invoke(
        ListResourceBookChaptersRequest $request,
        ResourceBook $book,
    ): AnonymousResourceCollection {
        $page = (int) $request->query('page', 1);

        $paginator = $book->chapters()
            ->orderBy('position')
            ->paginate(50, page: max(1, $page));

        return AdminResourceBookChapterResource::collection($paginator);
    }
}
