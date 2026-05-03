<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DataTransferObjects\SummaryQueryData;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SummariseResourceDownloadsAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(SummaryQueryData $query): array
    {
        if ($query->groupBy !== 'day') {
            throw new BadRequestHttpException(
                'long-range download summary requires MBA-030 rollups',
            );
        }

        // Compare on calendar days so an inclusive 7-day window
        // (e.g. Mon 00:00 → Sun 23:59) doesn't trip the boundary on
        // sub-day fractions.
        $rangeDays = (int) $query->from->startOfDay()->diffInDays($query->to->startOfDay()) + 1;

        if ($rangeDays > 7) {
            throw new BadRequestHttpException(
                'long-range download summary requires MBA-030 rollups',
            );
        }

        $builder = DB::table('resource_downloads')
            ->whereBetween('created_at', [$query->from, $query->to]);

        if ($query->downloadableType !== null) {
            $builder->where('downloadable_type', $query->downloadableType);
        }

        if ($query->language !== null) {
            $builder->where('language', $query->language);
        }

        $rows = $builder
            ->selectRaw('DATE(created_at) as `date`')
            ->selectRaw('downloadable_type')
            ->selectRaw('downloadable_id')
            ->selectRaw('language')
            ->selectRaw('COUNT(*) as `count`')
            ->selectRaw('COUNT(DISTINCT device_id) as unique_devices')
            ->groupBy(['date', 'downloadable_type', 'downloadable_id', 'language'])
            ->orderBy('date')
            ->orderBy('downloadable_type')
            ->orderBy('downloadable_id')
            ->orderBy('language')
            ->get();

        return $rows
            ->map(static fn (object $row): array => [
                'date' => (string) ($row->date ?? ''),
                'downloadable_type' => (string) ($row->downloadable_type ?? ''),
                'downloadable_id' => (int) ($row->downloadable_id ?? 0),
                'language' => $row->language === null ? null : (string) $row->language,
                'count' => (int) ($row->count ?? 0),
                'unique_devices' => (int) ($row->unique_devices ?? 0),
            ])
            ->all();
    }
}
