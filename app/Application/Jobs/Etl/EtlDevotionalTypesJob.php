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
 * Stage 2 — seed `devotional_types` from existing string-typed
 * `devotionals.type` values, and from any legacy Symfony
 * `devotional_type` table that survived reconcile. Backfill
 * `devotionals.type_id` so the canonical FK resolves; the legacy
 * `type` string column stays in place until MBA-032 cleanup.
 */
final class EtlDevotionalTypesJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_devotional_types';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('devotional_types') || ! Schema::hasTable('devotionals')) {
            return new EtlSubJobResult;
        }

        $created = $this->seedTypesFromDevotionalsEnum();
        $reporter->progress($importJob, 1, 3);

        $created += $this->seedTypesFromLegacyTable();
        $reporter->progress($importJob, 2, 3);

        $linked = $this->backfillDevotionalTypeIds();
        $reporter->progress($importJob, 3, 3);

        return new EtlSubJobResult(
            processed: $created + $linked,
            succeeded: $created + $linked,
        );
    }

    private function seedTypesFromDevotionalsEnum(): int
    {
        if (! Schema::hasColumn('devotionals', 'type')) {
            return 0;
        }

        $rows = DB::table('devotionals')
            ->select('type', 'language')
            ->whereNotNull('type')
            ->groupBy('type', 'language')
            ->get();

        $created = 0;

        foreach ($rows as $row) {
            $slug = (string) $row->type;
            if ($slug === '') {
                continue;
            }

            $exists = DB::table('devotional_types')
                ->where('slug', $slug)
                ->where('language', $row->language)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('devotional_types')->insert([
                'slug' => $slug,
                'title' => ucfirst($slug),
                'language' => $row->language,
                'position' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $created++;
        }

        return $created;
    }

    private function seedTypesFromLegacyTable(): int
    {
        if (! Schema::hasTable('devotional_type')) {
            return 0;
        }

        $rows = DB::table('devotional_type')->get();
        $created = 0;

        foreach ($rows as $row) {
            $slug = (string) ($row->slug ?? $row->name ?? '');
            if ($slug === '') {
                continue;
            }

            $exists = DB::table('devotional_types')
                ->where('slug', $slug)
                ->where('language', $row->language ?? null)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('devotional_types')->insert([
                'slug' => $slug,
                'title' => (string) ($row->title ?? $row->name ?? ucfirst($slug)),
                'language' => $row->language ?? null,
                'position' => (int) ($row->position ?? 0),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $created++;
        }

        return $created;
    }

    private function backfillDevotionalTypeIds(): int
    {
        if (! Schema::hasColumn('devotionals', 'type_id')) {
            return 0;
        }

        return (int) DB::affectingStatement(<<<'SQL'
            UPDATE devotionals d
            JOIN devotional_types dt ON dt.slug = d.type
                AND (dt.language IS NULL OR dt.language = d.language)
            SET d.type_id = dt.id
            WHERE d.type_id = 0 OR d.type_id IS NULL
        SQL);
    }
}
