<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompts\Commentary;

use App\Domain\AI\Prompts\Prompt;

/**
 * Commentary\Translate@1.0.0 — input HTML in the source language, output
 * idiomatic translation in the target language with the HTML structure
 * preserved exactly. Pinned to `claude-opus-4-7` because translation
 * quality is the most prompt-sensitive of the three commentary passes.
 */
final class TranslateV1 extends Prompt
{
    public const NAME = 'commentary_translate';

    public const VERSION = '1.0.0';

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional translator of Bible commentary. The input is an
HTML fragment in the source language identified per request. Your job
is to produce an idiomatic translation in the target language while
preserving the HTML structure exactly.

Strict rules:

1. Preserve the HTML structure exactly. Do not add, remove, reorder, or
   rename tags. Do not change attribute values.
2. Translate only the text content. Existing inline anchors, classes,
   and other attributes pass through unchanged.
3. Bible references appearing in the source as plain text (e.g.
   "John 3:16", "Ioan 3:16") must be rewritten in the target
   language's conventional form. Do not attempt to wrap them in
   anchors — reference linking is a separate downstream pass.
4. Preserve theological terminology accurately. When a word has an
   established translation in the target language's Christian
   vocabulary, prefer it over a literal calque.
5. Return only the translated HTML. No markdown fences, no preamble.
PROMPT;
    }

    /**
     * @param  array{html?: string, source_language?: string, target_language?: string}  $payload
     */
    public function userMessage(array $payload): string
    {
        $html = (string) ($payload['html'] ?? '');
        $sourceLanguage = (string) ($payload['source_language'] ?? '');
        $targetLanguage = (string) ($payload['target_language'] ?? '');

        return <<<MESSAGE
Source language: {$sourceLanguage}
Target language: {$targetLanguage}

Translate the HTML below into the target language while preserving the
structure exactly. Return only the resulting HTML — no code fences, no
commentary.

HTML:
{$html}
MESSAGE;
    }

    public function model(): string
    {
        return 'claude-opus-4-7';
    }
}
