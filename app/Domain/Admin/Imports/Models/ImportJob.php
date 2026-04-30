<?php

declare(strict_types=1);

namespace App\Domain\Admin\Imports\Models;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Models\User;
use Database\Factories\ImportJobFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracker row for a long-running admin import. The job worker that
 * performs the actual work updates `status` / `progress` / `error`;
 * the admin polls these via `GET /api/v1/admin/imports/{job}`.
 *
 * @property int $id
 * @property string $type
 * @property ImportJobStatus $status
 * @property int $progress
 * @property array<string, mixed>|null $payload
 * @property ?string $error
 * @property ?int $user_id
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?User $owner
 */
#[UseFactory(ImportJobFactory::class)]
final class ImportJob extends Model
{
    /** @use HasFactory<ImportJobFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportJobStatus::class,
            'payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
