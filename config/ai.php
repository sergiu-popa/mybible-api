<?php

declare(strict_types=1);

use App\Domain\AI\Prompts\AddReferences\V1 as AddReferencesV1;

return [

    /*
    |--------------------------------------------------------------------------
    | Default model
    |--------------------------------------------------------------------------
    |
    | Used when a prompt does not pin a specific model. Per-row latency-bound
    | calls (commentary correction, AddReferences) lean Sonnet for cost; rare
    | translation jobs override per call.
    */
    'model' => [
        'default' => env('AI_DEFAULT_MODEL', 'claude-sonnet-4-6'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request timeouts and retry policy
    |--------------------------------------------------------------------------
    */
    'request' => [
        'timeout_seconds' => (int) env('AI_REQUEST_TIMEOUT_SECONDS', 60),
    ],

    'retry' => [
        'max_attempts' => (int) env('AI_RETRY_MAX_ATTEMPTS', 3),
        // Pre-attempt sleeps in milliseconds. The first sleep is consumed
        // before retry #2; the array length must be >= max_attempts - 1.
        'backoff_ms' => [500, 2000, 5000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Bible version fallback
    |--------------------------------------------------------------------------
    |
    | Used when a language has no `language_settings.default_bible_version`
    | configured AND the caller did not pin a version on the request.
    */
    'default_bible_version_fallback' => env('AI_DEFAULT_BIBLE_VERSION_FALLBACK', 'VDC'),

    /*
    |--------------------------------------------------------------------------
    | Versioned prompt registry
    |--------------------------------------------------------------------------
    |
    | Map of `prompt_name => [version => Prompt::class]`. Calling code pins
    | the version explicitly — there is no implicit "latest". Keep the FQCN
    | imported above so static analysis catches typos.
    */
    'prompts' => [
        'add_references' => [
            '1.0.0' => AddReferencesV1::class,
        ],
    ],

];
