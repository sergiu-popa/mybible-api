<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use App\Application\Support\CommentaryBatchRunner;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Commentary\Actions\TranslateCommentaryAction;
use App\Domain\Commentary\Actions\TranslateCommentaryTextAction;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Async per-row translation — iterates the source commentary's `plain`
 * rows and invokes {@see TranslateCommentaryTextAction} into a
 * pre-prepared target commentary. The target itself is created /
 * cleared by {@see TranslateCommentaryAction}
 * inside the controller, so by the time this job runs the target's
 * texts are empty.
 */
final class TranslateCommentaryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $importJobId,
        public readonly int $sourceCommentaryId,
        public readonly int $targetCommentaryId,
        public readonly ?int $triggeredByUserId = null,
    ) {
        $this->onConnection('database');
        $this->onQueue('ai');
    }

    public function handle(
        CommentaryBatchRunner $runner,
        TranslateCommentaryTextAction $action,
    ): void {
        $importJob = ImportJob::query()->find($this->importJobId);
        if (! $importJob instanceof ImportJob) {
            return;
        }

        $target = Commentary::query()->find($this->targetCommentaryId);
        if (! $target instanceof Commentary) {
            throw new RuntimeException(sprintf(
                'TranslateCommentaryJob: target commentary #%d not found.',
                $this->targetCommentaryId,
            ));
        }

        $query = CommentaryText::query()
            ->with('commentary')
            ->where('commentary_id', $this->sourceCommentaryId)
            ->whereNotNull('plain');

        $userId = $this->triggeredByUserId;

        $runner->run($importJob, $query, static function ($row) use ($action, $target, $userId): void {
            /** @var CommentaryText $row */
            $action->execute($target, $row, $userId);
        });
    }
}
