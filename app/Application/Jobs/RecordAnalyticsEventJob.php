<?php

declare(strict_types=1);

namespace App\Application\Jobs;

use App\Domain\Analytics\DataTransferObjects\IngestEventData;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Models\AnalyticsEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists one analytics_events row from a serialised IngestEventData
 * + ResourceDownloadContextData. Lightweight on purpose (single
 * insert, no joins) so a high event-rate spike doesn't block the
 * `mybible-api-worker`. Failures are logged and swallowed after the
 * configured retries so a poison row cannot back-pressure the queue.
 */
final class RecordAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 30];

    public function __construct(
        public readonly IngestEventData $event,
        public readonly ResourceDownloadContextData $context,
        public readonly ?string $appVersion,
    ) {}

    public function handle(): void
    {
        AnalyticsEvent::query()->create([
            'event_type' => $this->event->eventType->value,
            'subject_type' => $this->event->subjectType,
            'subject_id' => $this->event->subjectId,
            'user_id' => $this->context->userId,
            'device_id' => $this->context->deviceId,
            'language' => $this->event->language ?? $this->context->language,
            'source' => $this->context->source?->value,
            'app_version' => $this->appVersion,
            'metadata' => $this->event->metadata,
            'occurred_at' => $this->event->occurredAt,
            'created_at' => Carbon::now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('RecordAnalyticsEventJob failed', [
            'event_type' => $this->event->eventType->value,
            'exception' => $exception->getMessage(),
        ]);
    }
}
