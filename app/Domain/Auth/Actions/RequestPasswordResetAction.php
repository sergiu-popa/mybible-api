<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DataTransferObjects\RequestPasswordResetData;
use Illuminate\Support\Facades\Password;

final class RequestPasswordResetAction
{
    public function execute(RequestPasswordResetData $data): void
    {
        // The broker status is deliberately discarded so the caller can send
        // a uniform 200 response regardless of whether the email maps to an
        // existing user, preventing account-enumeration via response shape
        // or timing difference.
        Password::broker()->sendResetLink(['email' => $data->email]);
    }
}
