<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompts\Commentary;

use App\Domain\AI\Prompts\Prompt;

/**
 * Commentary\Correct@1.0.0 — input HTML in the source language, output the
 * same HTML with light language-only fixes (typos, awkward phrasing,
 * punctuation). Structure-preserving and conservative — when in doubt,
 * the prompt instructs the model to leave content untouched.
 */
final class CorrectV1 extends Prompt
{
    public const NAME = 'commentary_correct';

    public const VERSION = '1.0.0';

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a careful copy-editor working on Bible commentary HTML in the
language identified per request. Your job is to fix only language-level
issues: typos, awkward phrasing that obscures meaning, missing or
duplicate punctuation, inconsistent diacritics. You must NEVER change
the meaning of the original text or rewrite passages.

Strict rules:

1. Preserve the HTML structure exactly. Do not add, remove, reorder, or
   rename tags. Do not change attribute values. Whitespace inside text
   nodes may be normalised but tag boundaries must remain identical.
2. Fix only language-level issues. If a sentence reads awkwardly but is
   not actually incorrect, leave it as-is. When in doubt, do not change.
3. Do not add, remove, or rewrite Bible references. Reference linking is
   a separate downstream pass.
4. Do not introduce content that is not present in the input — no
   commentary, headers, summaries, or annotations of any kind.
5. Return only the modified HTML. No markdown fences, no preamble.
PROMPT;
    }

    /**
     * @param  array{html?: string, language?: string}  $payload
     */
    public function userMessage(array $payload): string
    {
        $html = (string) ($payload['html'] ?? '');
        $language = (string) ($payload['language'] ?? '');

        return <<<MESSAGE
Target language: {$language}

Apply conservative copy-editing to the HTML below. Return only the
resulting HTML — no code fences, no commentary.

HTML:
{$html}
MESSAGE;
    }
}
