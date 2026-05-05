<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use App\Application\Support\CommentaryBatchRunner;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Commentary\Actions\CorrectCommentaryTextAction;
use App\Domain\Commentary\DataTransferObjects\AICorrectCommentaryTextData;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Async correction batch — iterates a commentary's texts (optionally
 * filtered by `book` / `chapter`), invokes
 * {@see CorrectCommentaryTextAction} per row, and writes per-row
 * failures into `import_jobs.payload.failures`.
 *
 * Connection: `database`, queue: `ai`. Migrate to Horizon when MBA-031
 * lands.
 */
final class CorrectCommentaryBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{book?: string, chapter?: int}  $filters
     */
    public function __construct(
        public readonly int $importJobId,
        public readonly int $commentaryId,
        public readonly array $filters = [],
        public readonly ?int $triggeredByUserId = null,
    ) {
        $this->onConnection('database');
        $this->onQueue('ai');
    }

    public function handle(
        CommentaryBatchRunner $runner,
        CorrectCommentaryTextAction $action,
    ): void {
        $importJob = ImportJob::query()->find($this->importJobId);
        if (! $importJob instanceof ImportJob) {
            return;
        }

        $query = CommentaryText::query()
            ->with('commentary')
            ->where('commentary_id', $this->commentaryId);

        if (isset($this->filters['book']) && $this->filters['book'] !== '') {
            $query->where('book', $this->filters['book']);
        }
        if (isset($this->filters['chapter']) && (int) $this->filters['chapter'] > 0) {
            $query->where('chapter', (int) $this->filters['chapter']);
        }

        $userId = $this->triggeredByUserId;

        $runner->run($importJob, $query, static function ($row) use ($action, $userId): void {
            /** @var CommentaryText $row */
            $action->execute(new AICorrectCommentaryTextData(
                text: $row,
                triggeredByUserId: $userId,
            ));
        });
    }
}
