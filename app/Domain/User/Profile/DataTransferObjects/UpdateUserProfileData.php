<?php

declare(strict_types=1);

namespace App\Domain\User\Profile\DataTransferObjects;

use App\Domain\Shared\Enums\Language;

final readonly class UpdateUserProfileData
{
    public function __construct(
        public ?string $name,
        public ?Language $language,
        public ?string $preferredVersion,
    ) {}

    /**
     * @param  array{name?: string|null, language?: string|null, preferred_version?: string|null}  $payload
     */
    public static function from(array $payload): self
    {
        $language = $payload['language'] ?? null;

        return new self(
            name: $payload['name'] ?? null,
            language: is_string($language) ? Language::from($language) : null,
            preferredVersion: $payload['preferred_version'] ?? null,
        );
    }
}
