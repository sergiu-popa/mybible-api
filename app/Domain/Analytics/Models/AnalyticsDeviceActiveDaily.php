<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Database\Factories\AnalyticsDeviceActiveDailyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property string $device_id
 */
#[UseFactory(AnalyticsDeviceActiveDailyFactory::class)]
final class AnalyticsDeviceActiveDaily extends Model
{
    /** @use HasFactory<AnalyticsDeviceActiveDailyFactory> */
    use HasFactory;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'analytics_device_active_daily';

    protected $guarded = [];

    protected $primaryKey = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
