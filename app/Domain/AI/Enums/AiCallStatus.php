<?php

declare(strict_types=1);

namespace App\Domain\AI\Enums;

enum AiCallStatus: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Timeout = 'timeout';
}
