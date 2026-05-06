<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use App\Domain\ReadingPlans\Enums\FragmentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stage 2 — finalises the Symfony→Laravel reading-plans data shape:
 *   • wraps legacy non-JSON `name` / `description` strings as `{"ro": "..."}`
 *   • generates a unique `slug` from the Romanian name when missing
 *   • expands each legacy `reading_plan_days.passages` JSON column into
 *     one `reading_plan_day_fragments` row of `type='references'`
 *
 * Each pass is idempotent: rows already in the target shape are skipped.
 */
final class EtlReadingPlansJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_reading_plans';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('reading_plans')) {
            return new EtlSubJobResult;
        }

        $processed = 0;
        $succeeded = 0;

        $succeeded += $this->wrapPlanText();
        $processed += 1;
        $reporter->progress($importJob, 1, 3);

        $succeeded += $this->backfillSlugs();
        $processed += 1;
        $reporter->progress($importJob, 2, 3);

        $succeeded += $this->expandPassagesToFragments();
        $processed += 1;
        $reporter->progress($importJob, 3, 3);

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
        );
    }

    private function wrapPlanText(): int
    {
        // The reconcile migration converts these columns to `json`. If a
        // legacy plain-string survived it is invalid JSON, which surfaces
        // as a parse failure when the model reads it. Detect by attempting
        // a decode and rewriting in place.
        $count = 0;

        $rows = DB::table('reading_plans')->select(['id', 'name', 'description'])->get();

        foreach ($rows as $row) {
            $update = [];

            $name = $this->ensureLocaleMap($row->name);
            if ($name !== null) {
                $update['name'] = $name;
            }

            $description = $this->ensureLocaleMap($row->description);
            if ($description !== null) {
                $update['description'] = $description;
            }

            if ($update !== []) {
                $update['updated_at'] = now();
                DB::table('reading_plans')->where('id', $row->id)->update($update);
                $count++;
            }
        }

        return $count;
    }

    private function ensureLocaleMap(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded) && ! array_is_list($decoded)) {
            return null;
        }

        return json_encode(['ro' => $raw], JSON_UNESCAPED_UNICODE) ?: null;
    }

    private function backfillSlugs(): int
    {
        if (! Schema::hasColumn('reading_plans', 'slug')) {
            return 0;
        }

        $count = 0;

        $rows = DB::table('reading_plans')
            ->where(function ($query): void {
                $query->whereNull('slug')->orWhere('slug', '');
            })
            ->get(['id', 'name']);

        foreach ($rows as $row) {
            $name = json_decode((string) $row->name, true);
            $title = is_array($name) ? (string) ($name['ro'] ?? reset($name)) : (string) $row->name;

            if ($title === '') {
                continue;
            }

            $slug = $this->uniqueSlug(Str::slug($title), (int) $row->id);

            DB::table('reading_plans')
                ->where('id', $row->id)
                ->update(['slug' => $slug, 'updated_at' => now()]);

            $count++;
        }

        return $count;
    }

    private function uniqueSlug(string $base, int $excludeId): string
    {
        $slug = $base !== '' ? $base : 'reading-plan';
        $candidate = $slug;
        $suffix = 2;

        while (
            DB::table('reading_plans')
                ->where('slug', $candidate)
                ->where('id', '!=', $excludeId)
                ->exists()
        ) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function expandPassagesToFragments(): int
    {
        if (
            ! Schema::hasTable('reading_plan_day_fragments')
            || ! Schema::hasColumn('reading_plan_days', 'passages')
        ) {
            return 0;
        }

        $count = 0;

        DB::table('reading_plan_days')
            ->whereNotNull('passages')
            ->orderBy('id')
            ->chunkById(500, function ($days) use (&$count): void {
                foreach ($days as $day) {
                    $exists = DB::table('reading_plan_day_fragments')
                        ->where('reading_plan_day_id', $day->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $passages = json_decode((string) $day->passages, true);
                    if (! is_array($passages)) {
                        continue;
                    }

                    /** @var list<string> $references */
                    $references = array_values(array_filter(array_map(
                        static fn ($value): string => is_string($value) ? trim($value) : '',
                        $passages,
                    ), static fn (string $value): bool => $value !== ''));

                    if ($references === []) {
                        continue;
                    }

                    DB::table('reading_plan_day_fragments')->insert([
                        'reading_plan_day_id' => $day->id,
                        'position' => 0,
                        'type' => FragmentType::References->value,
                        'content' => json_encode($references, JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $count++;
                }
            });

        return $count;
    }
}
