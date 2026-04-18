<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait WithApiKeyClient
{
    protected string $apiKeyHeader = 'X-Api-Key';

    protected string $apiKeyValue = 'mobile-valid-key';

    protected function setUpApiKeyClient(): void
    {
        config()->set('api_keys.header', $this->apiKeyHeader);
        config()->set('api_keys.clients', [
            'mobile' => $this->apiKeyValue,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function apiKeyHeaders(): array
    {
        return [$this->apiKeyHeader => $this->apiKeyValue];
    }
}
