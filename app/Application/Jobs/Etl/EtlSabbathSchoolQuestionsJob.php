<?php

declare(strict_types=1);

namespace App\Application\Jobs\Etl;

use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 2 — converts each `sabbath_school_questions` row into a
 * `sabbath_school_segment_contents` row of `type='question'`, then
 * rewires `sabbath_school_answers` from question-keyed to
 * segment-content-keyed.
 *
 * The MBA-025 reconcile renames the legacy
 * `sabbath_school_answers.sabbath_school_question_id` column to
 * `segment_content_id` while preserving its values, so by the time this
 * sub-job runs the column already exists but still holds **legacy
 * question IDs** that must be rewritten to the new content row IDs.
 *
 * Idempotency:
 *   • Content rows are skipped when an existing `(segment_id,
 *     title='legacy_question_<id>')` row already points at the same
 *     question.
 *   • Answer rewiring is computed as a (legacy_id → new_id) mapping
 *     and applied via a single UPDATE-JOIN through a transient
 *     mapping table — no intra-pass collisions, and re-runs are no-ops
 *     because already-rewritten rows no longer match a legacy id.
 */
final class EtlSabbathSchoolQuestionsJob extends BaseEtlJob
{
    public static function slug(): string
    {
        return 'etl_sabbath_school_questions';
    }

    protected function execute(EtlJobReporter $reporter, ImportJob $importJob): EtlSubJobResult
    {
        if (
            ! Schema::hasTable('sabbath_school_questions')
            || ! Schema::hasTable('sabbath_school_segment_contents')
        ) {
            return new EtlSubJobResult;
        }

        $questions = DB::table('sabbath_school_questions')->orderBy('id')->get();
        $total = $questions->count();
        $processed = 0;
        $succeeded = 0;
        /** @var array<int, int> $legacyToContent */
        $legacyToContent = [];

        foreach ($questions as $question) {
            /** @var \stdClass $question */
            $processed++;

            $contentId = $this->resolveOrCreateContentRow($question);
            $legacyToContent[(int) $question->id] = $contentId;
            $succeeded++;

            if ($processed % 25 === 0) {
                $reporter->progress($importJob, $processed, $total);
            }
        }

        $this->rewireAnswers($legacyToContent);

        return new EtlSubJobResult(
            processed: $processed,
            succeeded: $succeeded,
        );
    }

    private function resolveOrCreateContentRow(object $question): int
    {
        /** @var \stdClass $question */
        $segmentId = (int) $question->sabbath_school_segment_id;
        $prompt = (string) ($question->prompt ?? '');
        $title = sprintf('legacy_question_%d', (int) $question->id);

        $existing = DB::table('sabbath_school_segment_contents')
            ->where('segment_id', $segmentId)
            ->where('title', $title)
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $maxPosition = DB::table('sabbath_school_segment_contents')
            ->where('segment_id', $segmentId)
            ->max('position') ?? -1;

        return (int) DB::table('sabbath_school_segment_contents')->insertGetId([
            'segment_id' => $segmentId,
            'type' => 'question',
            'title' => $title,
            'position' => (int) $maxPosition + 1,
            'content' => $prompt,
            'created_at' => $question->created_at ?? now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $legacyToContent  legacy question id → new content row id
     */
    private function rewireAnswers(array $legacyToContent): void
    {
        if ($legacyToContent === [] || ! Schema::hasTable('sabbath_school_answers')) {
            return;
        }

        $column = $this->resolveAnswersTargetColumn();
        if ($column === null) {
            return;
        }

        $mappingTable = $this->ensureMappingTable($legacyToContent);

        try {
            // Single UPDATE-JOIN: MySQL evaluates the join as a snapshot
            // before applying any writes, so a `new_id` that happens to
            // collide with another row's `legacy_id` cannot bleed across
            // the rewrite.
            DB::affectingStatement(sprintf(
                'UPDATE sabbath_school_answers a
                 JOIN %s m ON a.%s = m.legacy_id
                 SET a.%s = m.new_id',
                $mappingTable,
                $column,
                $column,
            ));
        } finally {
            Schema::dropIfExists($mappingTable);
        }
    }

    private function resolveAnswersTargetColumn(): ?string
    {
        // Pre-MBA-025 shape: legacy column still around.
        if (Schema::hasColumn('sabbath_school_answers', 'sabbath_school_question_id')) {
            return 'sabbath_school_question_id';
        }

        // Post-MBA-025 shape: column was renamed; values still carry
        // legacy question ids until this sub-job rewrites them.
        if (Schema::hasColumn('sabbath_school_answers', 'segment_content_id')) {
            return 'segment_content_id';
        }

        return null;
    }

    /**
     * @param  array<int, int>  $legacyToContent
     */
    private function ensureMappingTable(array $legacyToContent): string
    {
        $table = 'tmp_etl_question_content_map_' . substr(md5(uniqid('', true)), 0, 8);

        Schema::create($table, function ($blueprint): void {
            $blueprint->unsignedBigInteger('legacy_id')->primary();
            $blueprint->unsignedBigInteger('new_id');
        });

        $rows = [];
        foreach ($legacyToContent as $legacy => $new) {
            $rows[] = ['legacy_id' => $legacy, 'new_id' => $new];
        }

        // Bulk insert in chunks to keep the wire payload bounded for
        // larger run sizes.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        return $table;
    }
}
