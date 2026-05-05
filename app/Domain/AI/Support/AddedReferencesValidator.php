<?php

declare(strict_types=1);

namespace App\Domain\AI\Support;

use App\Domain\Reference\Exceptions\InvalidReferenceException;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Security\Models\SecurityEvent;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Walks the AI-returned HTML and accepts only `<a class="reference" href>`
 * tags whose href parses through {@see ReferenceParser}. Any other anchor
 * is unwrapped (its inner text is preserved); each stripped anchor writes
 * one `security_events` row so we can audit upstream misbehaviour.
 *
 * Returns the cleaned HTML and the count of surviving valid references.
 */
final class AddedReferencesValidator
{
    public const SECURITY_EVENT = 'ai_invalid_reference_link_stripped';

    public function __construct(
        private readonly ReferenceParser $parser = new ReferenceParser,
    ) {}

    /**
     * @return array{html: string, references_added: int}
     */
    public function validate(string $html): array
    {
        if ($html === '') {
            return ['html' => '', 'references_added' => 0];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        $wrapper = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . $html
            . '</body></html>';

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML($wrapper, LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body instanceof DOMElement) {
            return ['html' => $html, 'references_added' => 0];
        }

        $anchors = iterator_to_array($dom->getElementsByTagName('a'));

        $kept = 0;
        foreach ($anchors as $anchor) {
            $class = $anchor->getAttribute('class');
            $href = $anchor->getAttribute('href');

            $hasReferenceClass = in_array(
                'reference',
                preg_split('/\s+/', trim($class)) ?: [],
                true,
            );

            if (! $hasReferenceClass || ! $this->isParsableHref($href)) {
                $this->logStrip($class, $href);
                $this->unwrap($anchor);

                continue;
            }

            $kept++;
        }

        $serialized = '';
        foreach ($body->childNodes as $child) {
            $serialized .= $dom->saveHTML($child);
        }

        return [
            'html' => $serialized,
            'references_added' => $kept,
        ];
    }

    private function isParsableHref(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        try {
            $this->parser->parse($href);

            return true;
        } catch (InvalidReferenceException) {
            return false;
        } catch (Throwable) {
            return false;
        }
    }

    private function unwrap(DOMElement $anchor): void
    {
        $parent = $anchor->parentNode;
        if (! $parent instanceof DOMNode) {
            return;
        }

        while ($anchor->firstChild !== null) {
            $parent->insertBefore($anchor->firstChild, $anchor);
        }

        $parent->removeChild($anchor);
    }

    private function logStrip(string $class, string $href): void
    {
        SecurityEvent::query()->create([
            'event' => self::SECURITY_EVENT,
            'reason' => 'AI returned an anchor that failed validation; unwrapped to plain text.',
            'affected_count' => 1,
            'metadata' => [
                'class' => $class,
                'href' => $href,
            ],
            'occurred_at' => Carbon::now(),
        ]);
    }
}
