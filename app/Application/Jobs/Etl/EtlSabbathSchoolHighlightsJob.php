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
 * Stage 2 (post Stage 2a) — convert legacy passage-string highlights into
 * offset-based highlights against the now-populated
 * `sabbath_school_segment_contents`. Unparseable rows are routed to
 * `sabbath_school_highlights_legacy` and a `security_events` row is
 * emitted (per MBA-025 §18) so an operator can audit.
 *
 * Depends on `EtlSabbathSchoolContentJob` and
 * `EtlSabbathSchoolQuestionsJob` having completed first; the
 * orchestrator places this in a separate Stage 2b batch.
 */
final class EtlSabbathSchoolHighlightsJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_sabbath_school_highlights';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (! Schema::hasTable('sabbath_school_highlights')) {
            return new EtlSubJobResult;
        }

        $legacyHighlights = DB::table('sabbath_school_highlights')
            ->whereNotNull('passage')
            ->whereNull('segment_content_id')
            ->orderBy('id')
            ->get();

        $total = $legacyHighlights->count();
        $processed = 0;
        $succeeded = 0;
        $skipped = 0;
        /** @var list<array{row?: int|string, message: string}> $errors */
        $errors = [];

        foreach ($legacyHighlights as $highlight) {
            /** @var \stdClass $highlight */
            $processed++;
            $resolved = $this->resolveOffsets((int) $highlight->sabbath_school_segment_id, (string) $highlight->passage);

            if ($resolved === null) {
                $skipped++;
                $this->archive($highlight);
                $errors[] = [
                    'row' => (int) $highlight->id,
                    'message' => 'Unparseable passage; archived to sabbath_school_highlights_legacy.',
                ];
                $reporter->appendError($importJob, $errors[count($errors) - 1]);

                continue;
            }

            DB::table('sabbath_school_highlights')
                ->where('id', $highlight->id)
                ->update([
                    'segment_content_id' => $resolved['segment_content_id'],
                    'start_position' => $resolved['start_position'],
                    'end_position' => $resolved['end_position'],
                    'updated_at' => Carbon::now(),
                ]);

            $succeeded++;

            if ($processed % 25 === 0) {
                $reporter->progress($importJob, $processed, $total);
            }
        }

        if ($skipped > 0) {
            $this->emitSecurityEvent($skipped);
        }

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
            skipped: $skipped,
            errors: $errors,
        );
    }

    /**
     * @return array{segment_content_id: int, start_position: int, end_position: int}|null
     */
    private function resolveOffsets(int $segmentId, string $passage): ?array
    {
        if ($passage === '') {
            return null;
        }

        $contents = DB::table('sabbath_school_segment_contents')
            ->where('segment_id', $segmentId)
            ->orderBy('position')
            ->get(['id', 'content']);

        foreach ($contents as $content) {
            $body = (string) $content->content;
            $start = mb_strpos($body, $passage);

            if ($start === false) {
                continue;
            }

            return [
                'segment_content_id' => (int) $content->id,
                'start_position' => $start,
                'end_position' => $start + mb_strlen($passage),
            ];
        }

        return null;
    }

    private function archive(object $highlight): void
    {
        if (! Schema::hasTable('sabbath_school_highlights_legacy')) {
            return;
        }

        /** @var \stdClass $highlight */
        DB::table('sabbath_school_highlights_legacy')->insertOrIgnore([
            'id' => $highlight->id,
            'user_id' => $highlight->user_id,
            'sabbath_school_segment_id' => $highlight->sabbath_school_segment_id,
            'passage' => $highlight->passage,
            'created_at' => $highlight->created_at,
            'archived_at' => Carbon::now(),
        ]);
    }

    private function emitSecurityEvent(int $count): void
    {
        if (! Schema::hasTable('security_events')) {
            return;
        }

        DB::table('security_events')->insert([
            'event' => 'sabbath_school_highlights_unparseable',
            'reason' => sprintf('%d Sabbath School highlight(s) archived to legacy table; manual review required.', $count),
            'affected_count' => $count,
            'metadata' => json_encode([
                'sub_job' => self::slug(),
            ], JSON_UNESCAPED_UNICODE),
            'occurred_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);
    }
}
