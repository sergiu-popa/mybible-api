<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class ShowLivenessController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'ts' => Carbon::now()->toIso8601String(),
        ]);
    }
}
