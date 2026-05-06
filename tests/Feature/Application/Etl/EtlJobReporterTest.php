<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Etl;

use App\Domain\Admin\Imports\Enums\ImportJobStatus;
use App\Domain\Admin\Imports\Models\ImportJob;
use App\Domain\Migration\Etl\DataTransferObjects\EtlSubJobResult;
use App\Domain\Migration\Etl\Support\EtlJobReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class EtlJobReporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function start_short_circuits_when_a_succeeded_row_exists_for_the_type(): void
    {
        $reporter = new EtlJobReporter;

        $first = ImportJob::query()->create([
            'type' => 'etl_news_language_default',
            'status' => ImportJobStatus::Succeeded,
            'progress' => 100,
            'payload' => ['original' => true],
        ]);

        $second = $reporter->start('etl_news_language_default');

        $this->assertSame($first->id, $second->id, 'Reporter must surface the existing succeeded row.');
    }

    #[Test]
    public function complete_picks_partial_when_errors_coexist_with_successes(): void
    {
        $reporter = new EtlJobReporter;
        $job = $reporter->start('etl_test');

        $reporter->complete(
            $job,
            new EtlSubJobResult(
                processed: 10,
                succeeded: 8,
                errors: [['row' => 1, 'message' => 'failed row']],
            ),
        );

        $job->refresh();
        $this->assertSame(ImportJobStatus::Partial, $job->status);
        $this->assertSame(100, $job->progress);

        $payload = $job->payload;
        $this->assertIsArray($payload);
        $this->assertSame(8, $payload['succeeded']);
        $this->assertSame(1, $payload['error_count']);
    }

    #[Test]
    public function complete_picks_failed_when_only_errors_were_recorded(): void
    {
        $reporter = new EtlJobReporter;
        $job = $reporter->start('etl_test');

        $reporter->complete(
            $job,
            new EtlSubJobResult(
                processed: 5,
                succeeded: 0,
                errors: [['row' => 1, 'message' => 'all failed']],
            ),
        );

        $job->refresh();
        $this->assertSame(ImportJobStatus::Failed, $job->status);
    }

    #[Test]
    public function fail_records_exception_message_on_the_row(): void
    {
        $reporter = new EtlJobReporter;
        $job = $reporter->start('etl_test');

        $reporter->fail($job, new RuntimeException('boom'));

        $job->refresh();
        $this->assertSame(ImportJobStatus::Failed, $job->status);
        $this->assertSame('boom', $job->error);
    }

    #[Test]
    public function partial_status_is_terminal(): void
    {
        $this->assertTrue(ImportJobStatus::Partial->isTerminal());
    }
}
