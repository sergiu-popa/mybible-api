<?php

declare(strict_types=1);

namespace App\Domain\Devotional\Enums;

enum DevotionalType: string
{
    case Adults = 'adults';
    case Kids = 'kids';
}
