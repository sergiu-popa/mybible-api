<?php

declare(strict_types=1);

namespace App\Domain\EducationalResources\Enums;

enum ResourceType: string
{
    case Article = 'article';
    case Video = 'video';
    case Pdf = 'pdf';
    case Audio = 'audio';
}
