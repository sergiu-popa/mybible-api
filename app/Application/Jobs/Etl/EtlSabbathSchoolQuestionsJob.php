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
 * rewires `sabbath_school_answers.segment_content_id` (FK rename
 * happened in MBA-025) onto the newly-inserted content row.
 *
 * Idempotent: tracks the question→content mapping inside the
 * `import_jobs.payload['question_to_content']` array and skips any
 * question already referenced by an existing content row of the same
 * segment+title pair.
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

        foreach ($questions as $question) {
            /** @var \stdClass $question */
            $processed++;

            $contentId = $this->resolveOrCreateContentRow($question);
            $this->rewireAnswers((int) $question->id, $contentId);
            $succeeded++;

            if ($processed % 25 === 0) {
                $reporter->progress($importJob, $processed, $total);
            }
        }

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

    private function rewireAnswers(int $legacyQuestionId, int $segmentContentId): void
    {
        if (! Schema::hasTable('sabbath_school_answers')) {
            return;
        }

        // Two possible shapes pre/post MBA-025: the answer table either
        // still carries a legacy `sabbath_school_question_id` column or
        // was already renamed to `segment_content_id`. Rewire whichever
        // we find — but do not overwrite an answer that already points
        // at a content row.
        if (Schema::hasColumn('sabbath_school_answers', 'sabbath_school_question_id')) {
            DB::table('sabbath_school_answers')
                ->where('sabbath_school_question_id', $legacyQuestionId)
                ->whereNull('segment_content_id')
                ->update(['segment_content_id' => $segmentContentId]);
        }
    }
}
