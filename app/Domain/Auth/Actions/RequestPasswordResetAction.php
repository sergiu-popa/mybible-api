<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DataTransferObjects\RequestPasswordResetData;
use Illuminate\Support\Facades\Password;

final class RequestPasswordResetAction
{
    public function execute(RequestPasswordResetData $data): void
    {
        // The broker status is deliberately discarded so the caller returns
        // a uniform 200 regardless of whether the email maps to a real user,
        // preventing account enumeration via response shape. (A minor timing
        // channel still exists — known emails incur a DB insert into
        // password_reset_tokens, unknown ones short-circuit — accepted.)
        Password::broker()->sendResetLink(['email' => $data->email]);
    }
}
