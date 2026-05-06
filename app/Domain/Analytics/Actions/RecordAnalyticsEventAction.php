<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Application\Jobs\RecordAnalyticsEventJob;
use App\Domain\Analytics\DataTransferObjects\IngestEventData;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The single write entry point for analytics events. Dispatches one
 * `RecordAnalyticsEventJob` so HTTP threads never block on inserts.
 * Used by the ingest controller, server-side emission points
 * (auth/login, reading-plan lifecycle, QR scans, resource downloads),
 * and any future Listener.
 */
final class RecordAnalyticsEventAction
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function execute(
        EventType $eventType,
        ResourceDownloadContextData $context,
        ?Model $subject = null,
        ?array $metadata = null,
        ?CarbonImmutable $occurredAt = null,
        ?string $appVersion = null,
    ): void {
        $subjectType = null;
        $subjectId = null;

        if ($subject !== null) {
            $alias = Relation::getMorphAlias($subject::class);
            $subjectType = is_string($alias) && $alias !== $subject::class
                ? $alias
                : $subject::class;
            $subjectId = (int) $subject->getKey();
        }

        $event = new IngestEventData(
            eventType: $eventType,
            subjectType: $subjectType,
            subjectId: $subjectId,
            language: $context->language,
            metadata: $metadata,
            occurredAt: $occurredAt ?? CarbonImmutable::now(),
        );

        RecordAnalyticsEventJob::dispatch($event, $context, $appVersion);
    }
}
