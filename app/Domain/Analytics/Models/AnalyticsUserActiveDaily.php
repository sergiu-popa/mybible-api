<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Database\Factories\AnalyticsUserActiveDailyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property int $user_id
 */
#[UseFactory(AnalyticsUserActiveDailyFactory::class)]
final class AnalyticsUserActiveDaily extends Model
{
    /** @use HasFactory<AnalyticsUserActiveDailyFactory> */
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'analytics_user_active_daily';

    protected $guarded = [];

    protected $primaryKey = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'user_id' => 'integer',
        ];
    }
}
