<?php

declare(strict_types=1);

namespace App\Domain\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only audit row for security-relevant operational events.
 *
 * @property int $id
 * @property string $event
 * @property string $reason
 * @property int|null $affected_count
 * @property array<string, mixed>|null $metadata
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 */
final class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_SYMFONY_CUTOVER_FORCED_LOGOUT = 'symfony_cutover_forced_logout';

    /** @var list<string> */
    protected $fillable = [
        'event',
        'reason',
        'affected_count',
        'metadata',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'affected_count' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
