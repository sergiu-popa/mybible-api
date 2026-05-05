<?php

declare(strict_types=1);

namespace App\Support\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesTriggeringUser
{
    private function triggeringUserId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof User ? (int) $user->id : null;
    }
}
