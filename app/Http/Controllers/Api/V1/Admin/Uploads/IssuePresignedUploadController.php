<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Uploads;

use App\Domain\Admin\Uploads\Actions\IssuePresignedUploadAction;
use App\Http\Requests\Admin\Uploads\IssuePresignedUploadRequest;
use Illuminate\Http\JsonResponse;

final class IssuePresignedUploadController
{
    public function __invoke(
        IssuePresignedUploadRequest $request,
        IssuePresignedUploadAction $action,
    ): JsonResponse {
        $result = $action->execute($request->toData());

        return response()->json([
            'key' => $result->key,
            'upload_url' => $result->uploadUrl,
            'expires_at' => $result->expiresAt->toIso8601String(),
            'headers' => $result->headers,
        ], 201);
    }
}
