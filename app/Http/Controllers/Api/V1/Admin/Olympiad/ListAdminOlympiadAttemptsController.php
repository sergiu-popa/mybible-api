<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Olympiad;

use App\Domain\Olympiad\Actions\ListAdminOlympiadAttemptsAction;
use App\Http\Requests\Admin\Olympiad\ListAdminOlympiadAttemptsRequest;
use App\Http\Resources\Olympiad\OlympiadAttemptResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminOlympiadAttemptsController
{
    public function __invoke(
        ListAdminOlympiadAttemptsRequest $request,
        ListAdminOlympiadAttemptsAction $action,
    ): AnonymousResourceCollection {
        return OlympiadAttemptResource::collection(
            $action->handle($request->filter(), $request->pageNumber(), $request->perPage()),
        );
    }
}
