<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Mounts the Horizon dashboard at `/horizon` behind a Sanctum-resolved
 * super-admin gate. The dashboard is a deliberate, narrowly-scoped
 * exception to the JSON-only posture: it serves super-admins only and
 * is not a public API surface. The gate mirrors {@see EnsureSuperAdmin}.
 */
final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', static function (?User $user = null): bool {
            if (! $user instanceof User) {
                return false;
            }

            if (! $user->is_active) {
                return false;
            }

            if (! in_array('admin', $user->roles, true)) {
                return false;
            }

            return $user->is_super;
        });
    }
}
