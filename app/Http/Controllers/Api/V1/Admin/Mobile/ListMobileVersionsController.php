<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Mobile;

use App\Domain\Mobile\Actions\ListMobileVersionsAction;
use App\Http\Requests\Admin\Mobile\ListMobileVersionsRequest;
use App\Http\Resources\Mobile\MobileVersionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListMobileVersionsController
{
    public function __invoke(
        ListMobileVersionsRequest $request,
        ListMobileVersionsAction $action,
    ): AnonymousResourceCollection {
        return MobileVersionResource::collection(
            $action->handle($request->platform(), $request->pageNumber(), $request->perPage()),
        );
    }
}
