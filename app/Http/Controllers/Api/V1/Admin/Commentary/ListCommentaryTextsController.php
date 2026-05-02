<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Commentary;

use App\Domain\Commentary\Models\Commentary;
use App\Http\Requests\Admin\Commentary\ListCommentaryTextsRequest;
use App\Http\Resources\Commentary\AdminCommentaryTextResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCommentaryTextsController
{
    public function __invoke(
        ListCommentaryTextsRequest $request,
        Commentary $commentary,
    ): AnonymousResourceCollection {
        $paginator = $commentary->texts()
            ->forBookChapter($request->book(), $request->chapter())
            ->paginate($request->perPage());

        return AdminCommentaryTextResource::collection($paginator);
    }
}
