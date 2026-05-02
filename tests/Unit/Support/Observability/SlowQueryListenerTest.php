<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Observability;

use App\Support\Observability\SlowQueryListener;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class SlowQueryListenerTest extends TestCase
{
    public function test_a_slow_query_writes_to_the_slow_query_channel(): void
    {
        $channel = $this->fakeSlowQueryChannel();

        SlowQueryListener::handle($this->makeQuery(timeMs: 600.0));

        $this->assertNotEmpty(
            $channel->records,
            'A query slower than the threshold must produce a slow_query log record.',
        );
        $this->assertSame('warning', $channel->records[0]['level']);
        $this->assertSame('slow_query', $channel->records[0]['message']);
        $this->assertSame(600.0, $channel->records[0]['context']['time_ms']);
    }

    public function test_a_fast_query_does_not_write_anything(): void
    {
        $channel = $this->fakeSlowQueryChannel();

        SlowQueryListener::handle($this->makeQuery(timeMs: 100.0));
        SlowQueryListener::handle($this->makeQuery(timeMs: 499.0));
        SlowQueryListener::handle($this->makeQuery(timeMs: 500.0));

        $this->assertSame([], $channel->records, 'Fast queries must not produce any records.');
    }

    public function test_threshold_is_500_milliseconds(): void
    {
        $this->assertSame(500, SlowQueryListener::THRESHOLD_MS);
    }

    private function fakeSlowQueryChannel(): SlowQueryChannelSpy
    {
        $channel = new SlowQueryChannelSpy;

        Log::shouldReceive('channel')
            ->with('slow_query')
            ->andReturn($channel);

        return $channel;
    }

    private function makeQuery(float $timeMs): QueryExecuted
    {
        return new QueryExecuted(
            sql: 'select * from t',
            bindings: [1, 2],
            time: $timeMs,
            connection: $this->app->make(Connection::class),
        );
    }
}

final class SlowQueryChannelSpy
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->records[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }
}
