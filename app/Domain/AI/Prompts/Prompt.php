<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompts;

/**
 * Versioned prompt template. Subclasses are stateless — heredoc strings
 * only — and live under `App\Domain\AI\Prompts\<Name>\V<n>.php`.
 *
 * The registry pins the (NAME, VERSION) pair; calling code never asks
 * for "latest". Each consumed row records the version that produced it.
 */
abstract class Prompt
{
    /** Stable name shared across versions, e.g. `add_references`. */
    public const NAME = '';

    /** Semver-ish version pinned by callers and recorded on every row. */
    public const VERSION = '';

    /**
     * Static portion of the prompt. Subject to caching: identical bytes
     * across calls produce a cache hit on the upstream side.
     */
    abstract public function systemPrompt(): string;

    /**
     * Per-call message. Cache-bypass — every call ships its own.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract public function userMessage(array $payload): string;

    /**
     * Optional per-prompt model override. Returning `null` (the default)
     * tells callers to fall back to `config('ai.model.default')`. Use
     * this to pin a specific model when the prompt only makes sense
     * against it (e.g. a translation prompt that needs Opus).
     */
    public function model(): ?string
    {
        return null;
    }
}
