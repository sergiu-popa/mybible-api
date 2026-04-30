<?php

declare(strict_types=1);

namespace App\Domain\Admin\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Password;

final class SendAdminPasswordResetAction
{
    /**
     * Issue a password-reset email for the target admin. Discards the
     * broker status so callers always see a uniform success — the
     * super-admin already knows the account exists at this point, but
     * keeping the response uniform avoids accidental disclosure if the
     * endpoint ever moves outside the super-admin gate.
     */
    public function execute(User $user): void
    {
        Password::broker()->sendResetLink(['email' => $user->email]);
    }
}
