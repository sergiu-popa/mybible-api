<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DataTransferObjects\AnalyticsRangeQueryData;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Computes DAU and 28-day rolling MAU for both authenticated users
 * (`analytics_user_active_daily`) and anonymous devices
 * (`analytics_device_active_daily`) across the requested range.
 *
 * MAU at date D = distinct user_ids active in `[D - 27, D]`. The
 * rolling window does NOT clip to the requested `from` — that would
 * give a misleadingly low MAU for the first 27 days of any window.
 */
final class ComputeDauMauAction
{
    /**
     * @return array<int, array{
     *     date: string,
     *     dau_users: int,
     *     mau_users: int,
     *     dau_devices: int,
     *     mau_devices: int,
     * }>
     */
    public function execute(AnalyticsRangeQueryData $query): array
    {
        $period = CarbonPeriod::create(
            $query->from->startOfDay(),
            '1 day',
            $query->to->startOfDay(),
        );

        $out = [];

        foreach ($period as $day) {
            /** @var CarbonInterface $day */
            $immDay = CarbonImmutable::parse($day->toDateString());
            $dateString = $immDay->toDateString();
            $mauStart = $immDay->subDays(27)->toDateString();

            $dauUsers = (int) DB::table('analytics_user_active_daily')
                ->where('date', $dateString)
                ->count();
            $mauUsers = (int) DB::table('analytics_user_active_daily')
                ->whereBetween('date', [$mauStart, $dateString])
                ->distinct()
                ->count('user_id');

            $dauDevices = (int) DB::table('analytics_device_active_daily')
                ->where('date', $dateString)
                ->count();
            $mauDevices = (int) DB::table('analytics_device_active_daily')
                ->whereBetween('date', [$mauStart, $dateString])
                ->distinct()
                ->count('device_id');

            $out[] = [
                'date' => $dateString,
                'dau_users' => $dauUsers,
                'mau_users' => $mauUsers,
                'dau_devices' => $dauDevices,
                'mau_devices' => $mauDevices,
            ];
        }

        return $out;
    }
}
