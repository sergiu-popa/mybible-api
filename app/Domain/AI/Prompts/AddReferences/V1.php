<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompts\AddReferences;

use App\Domain\AI\Prompts\Prompt;
use App\Domain\Reference\Data\BibleBookCatalog;

/**
 * AddReferences@1.0.0 — input HTML in, HTML out with detected Bible
 * references wrapped in `<a class="reference" href="…">` tags.
 *
 * The system prompt embeds the canonical USFM book list and the
 * reference-format spec, so it is identical across every call and
 * benefits from upstream prompt caching. The user message carries
 * only the per-call HTML, target language, and Bible-version slug.
 */
final class V1 extends Prompt
{
    public const NAME = 'add_references';

    public const VERSION = '1.0.0';

    public function systemPrompt(): string
    {
        $bookList = implode(', ', array_keys(BibleBookCatalog::BOOKS));

        return <<<PROMPT
You are a Bible-reference linker. The input is an HTML fragment in the
target language. Your job is to find every Bible reference inside the
HTML's text content and wrap each one in an anchor tag of the form:

    <a class="reference" href="BOOK.CH:V[-CH:V][.VER]">visible text</a>

Strict rules:

1. Only modify text content — never change the HTML structure or
   existing attributes of any tag. Do not introduce new tags other
   than the `<a class="reference">` wrappers.
2. The `href` attribute MUST follow the canonical reference format:
   `BOOK.CHAPTER:VERSE[-CHAPTER:VERSE][.VERSION]`
   - `BOOK` is one of the USFM abbreviations listed below.
   - `CHAPTER` and `VERSE` are positive integers.
   - The closing range part is optional. If the range is within a
     single chapter, use `BOOK.CH:V-V`. If it crosses chapters, use
     `BOOK.CH:V-CH:V`.
   - The trailing `.VERSION` is appended only when the caller has
     supplied a Bible version slug.
3. Always include `class="reference"` on every anchor you add.
4. Never wrap a reference that is already inside an existing
   `<a class="reference">` tag — leave it untouched.
5. If a candidate reference cannot be normalised into the canonical
   format above, leave the source text unchanged.
6. Return only the modified HTML, with no surrounding commentary,
   markdown fences, or explanation.

USFM book abbreviations (use exactly one of these as `BOOK`):
{$bookList}
PROMPT;
    }

    /**
     * @param  array{html?: string, language?: string, bible_version_abbreviation?: string|null}  $payload
     */
    public function userMessage(array $payload): string
    {
        $html = (string) ($payload['html'] ?? '');
        $language = (string) ($payload['language'] ?? '');
        $version = $payload['bible_version_abbreviation'] ?? null;
        $versionLine = is_string($version) && $version !== ''
            ? "Bible version slug to append after the chapter:verse range: {$version}"
            : 'No Bible version slug — emit hrefs without the trailing `.VERSION` suffix.';

        return <<<MESSAGE
Target language: {$language}
{$versionLine}

Wrap every Bible reference in the HTML below. Return only the resulting
HTML. Do not wrap the response in code fences.

HTML:
{$html}
MESSAGE;
    }
}
