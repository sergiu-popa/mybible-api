<?php

declare(strict_types=1);

namespace App\Domain\Admin\Imports\Enums;

enum ImportJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Partial = 'partial';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Partial => true,
            self::Pending, self::Running => false,
        };
    }
}
