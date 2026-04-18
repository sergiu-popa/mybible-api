<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Enums;

enum FragmentType: string
{
    case Html = 'html';
    case References = 'references';
}
