<?php

declare(strict_types=1);

namespace App\Domain\Collections\Actions;

use App\Domain\Collections\DataTransferObjects\ResolvedCollectionReference;
use App\Domain\Collections\Models\CollectionReference;
use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use App\Domain\Shared\Enums\Language;
use Illuminate\Support\Facades\Log;

final class ResolveCollectionReferencesAction
{
    public function __construct(
        private readonly ReferenceParser $parser = new ReferenceParser,
        private readonly ReferenceFormatter $formatter = new ReferenceFormatter,
    ) {}

    /**
     * @param  iterable<CollectionReference>  $references
     * @return array<int, ResolvedCollectionReference>
     */
    public function handle(iterable $references, Language $language): array
    {
        $resolved = [];

        foreach ($references as $reference) {
            $resolved[] = $this->resolveOne($reference, $language);
        }

        return $resolved;
    }

    private function resolveOne(CollectionReference $reference, Language $language): ResolvedCollectionReference
    {
        $raw = $reference->reference;

        try {
            $parsed = $this->parser->parse($raw);
        } catch (InvalidReferenceException $exception) {
            Log::warning('Failed to parse collection reference', [
                'collection_topic_id' => $reference->collection_topic_id,
                'collection_reference_id' => $reference->id,
                'raw' => $raw,
                'reason' => $exception->reason(),
            ]);

            return new ResolvedCollectionReference(
                raw: $raw,
                parsed: null,
                displayText: null,
                parseError: $exception->reason(),
            );
        }

        return new ResolvedCollectionReference(
            raw: $raw,
            parsed: array_map(fn (Reference $ref): array => $this->serializeReference($ref), $parsed),
            displayText: $this->buildDisplayText($parsed, $language),
            parseError: null,
        );
    }

    /**
     * @return array{book: string, chapter: int, verses: array<int, int>, version: ?string}
     */
    private function serializeReference(Reference $reference): array
    {
        return [
            'book' => $reference->book,
            'chapter' => $reference->chapter,
            'verses' => $reference->verses,
            'version' => $reference->version,
        ];
    }

    /**
     * @param  array<int, Reference>  $references
     */
    private function buildDisplayText(array $references, Language $language): string
    {
        return implode('; ', array_map(
            fn (Reference $ref): string => $this->formatter->toHumanReadable($ref, $language->value),
            $references,
        ));
    }
}
