<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use App\Domain\Analytics\DataTransferObjects\IngestBatchData;
use App\Domain\Analytics\DataTransferObjects\IngestEventData;
use App\Domain\Analytics\Enums\EventSubjectType;
use App\Domain\Analytics\Enums\EventType;
use App\Domain\Analytics\Support\ClientContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IngestAnalyticsEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.event_type' => ['required', 'string', Rule::enum(EventType::class)],
            'events.*.subject_type' => ['nullable', 'string', Rule::enum(EventSubjectType::class)],
            // subject_id is range-checked but not bound to an existing model
            // by design: existence checks would require N domain queries per
            // batch on the hot ingest path. Phantom subject_ids surface as
            // unjoined rollup rows (acceptable trade-off; revisit if the
            // dashboard surfaces them prominently).
            'events.*.subject_id' => ['nullable', 'integer', 'min:1'],
            'events.*.language' => ['nullable', 'string', 'size:2'],
            'events.*.metadata' => ['nullable', 'array'],
            'events.*.occurred_at' => ['required', 'date'],

            'device_id' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', Rule::in(['ios', 'android', 'web'])],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Apply per-event-type metadata rules and subject-type expectations
     * after the base shape passes. Validators are applied row-by-row
     * so a single bad event reports a precise error path.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var array<int, mixed> $events */
            $events = $this->input('events', []);

            foreach ($events as $index => $event) {
                if (! is_array($event)) {
                    continue;
                }

                $rawType = $event['event_type'] ?? null;
                if (! is_string($rawType)) {
                    continue;
                }

                $type = EventType::tryFrom($rawType);
                if ($type === null) {
                    continue;
                }

                $expectedSubject = $type->expectedSubjectType();
                $providedSubject = $event['subject_type'] ?? null;
                $providedSubjectId = $event['subject_id'] ?? null;

                if ($expectedSubject !== null) {
                    if ($providedSubject !== $expectedSubject->value) {
                        $validator->errors()->add(
                            sprintf('events.%d.subject_type', $index),
                            sprintf(
                                'Event "%s" requires subject_type "%s".',
                                $type->value,
                                $expectedSubject->value,
                            ),
                        );
                    }

                    if ($providedSubjectId === null) {
                        $validator->errors()->add(
                            sprintf('events.%d.subject_id', $index),
                            sprintf('Event "%s" requires subject_id.', $type->value),
                        );
                    }
                } elseif ($providedSubject !== null) {
                    $validator->errors()->add(
                        sprintf('events.%d.subject_type', $index),
                        sprintf('Event "%s" must not include a subject_type.', $type->value),
                    );
                }

                $rules = $type->metadataRules();
                if ($rules === []) {
                    continue;
                }

                $prefixed = [];
                foreach ($rules as $field => $rule) {
                    $prefixed[sprintf('events.%d.%s', $index, $field)] = $rule;
                }

                $perEvent = validator($this->all(), $prefixed);
                if ($perEvent->fails()) {
                    foreach ($perEvent->errors()->messages() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }
            }
        });
    }

    public function toBatchData(): IngestBatchData
    {
        /** @var array<int, array<string, mixed>> $events */
        $events = $this->validated('events');

        $context = ClientContextResolver::fromRequest($this);

        $appVersion = $this->validated('app_version');
        $appVersion = is_string($appVersion) && $appVersion !== '' ? $appVersion : null;

        $eventDtos = [];
        foreach ($events as $event) {
            $type = EventType::from((string) $event['event_type']);
            $subjectType = isset($event['subject_type']) && is_string($event['subject_type'])
                ? $event['subject_type']
                : null;
            $subjectId = isset($event['subject_id']) ? (int) $event['subject_id'] : null;
            $language = isset($event['language']) && is_string($event['language'])
                ? $event['language']
                : null;
            /** @var array<string, mixed>|null $metadata */
            $metadata = isset($event['metadata']) && is_array($event['metadata'])
                ? $event['metadata']
                : null;

            $eventDtos[] = new IngestEventData(
                eventType: $type,
                subjectType: $subjectType,
                subjectId: $subjectId,
                language: $language,
                metadata: $metadata,
                occurredAt: CarbonImmutable::parse((string) $event['occurred_at']),
            );
        }

        return new IngestBatchData(
            events: $eventDtos,
            context: $context,
            appVersion: $appVersion,
        );
    }
}
