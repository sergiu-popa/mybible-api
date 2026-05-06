<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — split each `sabbath_school_segments.content LONGTEXT` into
 * typed `sabbath_school_segment_contents` rows. Two sources:
 *
 *   1. Legacy `sb_content` rows (Symfony's typed shape) win — copied
 *      verbatim, normalised onto the new table.
 *   2. Segments without a `sb_content` ancestor (Laravel-era authored)
 *      get a single `type='text'` row holding the LONGTEXT body.
 *
 * Idempotent: a segment that already has any content row is skipped
 * entirely. Highlights ETL depends on this completing first
 * (orchestrator wires the dependency as separate stage).
 */
final class EtlSabbathSchoolContentJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_sabbath_school_content';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (
            ! Schema::hasTable('sabbath_school_segments')
            || ! Schema::hasTable('sabbath_school_segment_contents')
        ) {
            return new EtlSubJobResult;
        }

        $segments = DB::table('sabbath_school_segments')
            ->orderBy('id')
            ->get(['id', 'content']);

        $total = $segments->count();
        $processed = 0;
        $succeeded = 0;

        foreach ($segments as $segment) {
            /** @var \stdClass $segment */
            $processed++;

            $hasContent = DB::table('sabbath_school_segment_contents')
                ->where('segment_id', $segment->id)
                ->exists();

            if ($hasContent) {
                if ($processed % 25 === 0) {
                    $reporter->progress($importJob, $processed, $total);
                }

                continue;
            }

            $rows = $this->splitFromLegacy($segment->id);
            if ($rows === []) {
                $rows = $this->fallbackTextRow($segment);
            }

            if ($rows === []) {
                continue;
            }

            DB::table('sabbath_school_segment_contents')->insert($rows);
            $succeeded += count($rows);

            if ($processed % 25 === 0) {
                $reporter->progress($importJob, $processed, $total);
            }
        }

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function splitFromLegacy(int $segmentId): array
    {
        if (! Schema::hasTable('sb_content')) {
            return [];
        }

        $legacy = DB::table('sb_content')
            ->where('segment_id', $segmentId)
            ->orderBy('position')
            ->get();

        $rows = [];
        $position = 0;

        foreach ($legacy as $row) {
            $rows[] = [
                'segment_id' => $segmentId,
                'type' => (string) ($row->type ?? 'text'),
                'title' => $row->title ?? null,
                'position' => $position++,
                'content' => (string) ($row->content ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackTextRow(object $segment): array
    {
        /** @var \stdClass $segment */
        $body = (string) ($segment->content ?? '');
        if (trim($body) === '') {
            return [];
        }

        return [[
            'segment_id' => $segment->id,
            'type' => 'text',
            'title' => null,
            'position' => 0,
            'content' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]];
    }
}
