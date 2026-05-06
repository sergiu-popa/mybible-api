<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — if `mobile_versions` is empty, seed one row per
 * `(platform, kind)` from `config/mobile.php`. Idempotent: a populated
 * table is left alone so the seed cannot stomp operator-curated data.
 */
final class EtlMobileVersionsSeedJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_mobile_versions_seed';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('mobile_versions')) {
            return new EtlSubJobResult;
        }

        if (DB::table('mobile_versions')->count() > 0) {
            return new EtlSubJobResult(processed: 1, skipped: 1);
        }

        $now = Carbon::now();
        $rows = [];

        foreach (['ios', 'android'] as $platform) {
            $platformConfig = (array) config(sprintf('mobile.%s', $platform), []);

            foreach (['minimum_supported_version' => 'min', 'latest_version' => 'latest', 'force_update_below' => 'force'] as $key => $kind) {
                $version = (string) ($platformConfig[$key] ?? '');
                if ($version === '') {
                    continue;
                }

                $rows[] = [
                    'platform' => $platform,
                    'kind' => $kind,
                    'version' => $version,
                    'released_at' => null,
                    'release_notes' => null,
                    'store_url' => (string) ($platformConfig['update_url'] ?? '') ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return new EtlSubJobResult;
        }

        DB::table('mobile_versions')->insertOrIgnore($rows);
        $reporter->progress($importJob, count($rows), count($rows));

        return new EtlSubJobResult(
            processed: count($rows),
            succeeded: count($rows),
        );
    }
}
