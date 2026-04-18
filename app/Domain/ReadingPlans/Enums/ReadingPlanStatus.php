<?php

declare(strict_types=1);

namespace App\Domain\ReadingPlans\Enums;

enum ReadingPlanStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
