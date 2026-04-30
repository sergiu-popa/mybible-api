<?php

declare(strict_types=1);

namespace App\Domain\Admin\Users\DataTransferObjects;

final readonly class CreateAdminUserData
{
    /**
     * @param  list<string>  $languages
     */
    public function __construct(
        public string $name,
        public string $email,
        public array $languages,
        public ?string $uiLocale,
        public bool $isSuper,
    ) {}

    /**
     * @param  array{name: string, email: string, languages?: list<string>, ui_locale?: string|null, is_super?: bool}  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            name: $payload['name'],
            email: $payload['email'],
            languages: $payload['languages'] ?? [],
            uiLocale: $payload['ui_locale'] ?? null,
            isSuper: $payload['is_super'] ?? false,
        );
    }
}
