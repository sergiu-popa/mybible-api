<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Support;

use App\Application\Support\CommentaryBatchRunner;
use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Commentary\Models\CommentaryText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class CommentaryBatchRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_every_row_to_completion_when_all_succeed(): void
    {
        $rows = collect([1, 2, 3, 4, 5])->map(fn (int $p): CommentaryText => CommentaryText::factory()->create([
            'book' => 'GEN',
            'chapter' => 1,
            'position' => $p,
        ]));
        $importJob = ImportJob::factory()->create();

        $processed = [];
        $status = (new CommentaryBatchRunner)->run(
            $importJob,
            CommentaryText::query()->whereIn('id', $rows->pluck('id')->all()),
            function (CommentaryText $row) use (&$processed): void {
                $processed[] = (int) $row->id;
            },
        );

        self::assertSame(ImportJobStatus::Succeeded, $status);
        self::assertCount(5, $processed);

        $importJob->refresh();
        self::assertSame(ImportJobStatus::Succeeded, $importJob->status);
        self::assertSame(100, $importJob->progress);
    }

    public function test_partial_status_when_one_row_throws(): void
    {
        // 100 rows with chunk size 50 — exercises the multi-chunk
        // progress update path; offending row sits at position 50 to
        // straddle the chunk boundary and confirm the second chunk
        // still runs.
        $rows = collect(range(1, 100))->map(fn (int $p): CommentaryText => CommentaryText::factory()->create([
            'book' => 'GEN',
            'chapter' => 1,
            'position' => $p,
        ]));
        $importJob = ImportJob::factory()->create();

        /** @var CommentaryText $offending */
        $offending = $rows->get(49);
        $offendingId = (int) $offending->id;

        $processed = 0;
        $status = (new CommentaryBatchRunner)->run(
            $importJob,
            CommentaryText::query()->whereIn('id', $rows->pluck('id')->all()),
            function (CommentaryText $row) use ($offendingId, &$processed): void {
                $processed++;
                if ((int) $row->id === $offendingId) {
                    throw new RuntimeException('boom for row ' . $row->id);
                }
            },
        );

        self::assertSame(ImportJobStatus::Partial, $status);
        self::assertSame(100, $processed);

        $importJob->refresh();
        self::assertSame(ImportJobStatus::Partial, $importJob->status);

        $payload = is_array($importJob->payload) ? $importJob->payload : [];
        self::assertArrayHasKey('failures', $payload);
        self::assertCount(1, $payload['failures']);
        self::assertSame($offendingId, $payload['failures'][0]['id']);
        self::assertStringContainsString('boom', $payload['failures'][0]['message']);
    }

    public function test_empty_query_succeeds_immediately(): void
    {
        $importJob = ImportJob::factory()->create();

        $status = (new CommentaryBatchRunner)->run(
            $importJob,
            CommentaryText::query()->whereRaw('1 = 0'),
            static function (): void {
                // never invoked
            },
        );

        self::assertSame(ImportJobStatus::Succeeded, $status);
        self::assertSame(100, $importJob->refresh()->progress);
    }
}
