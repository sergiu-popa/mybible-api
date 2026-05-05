<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Prompts;

use App\Domain\AI\Exceptions\UnknownPromptException;
use App\Domain\AI\Prompts\AddReferences\V1 as AddReferencesV1;
use App\Domain\AI\Prompts\PromptRegistry;
use Illuminate\Container\Container;
use Tests\TestCase;

final class PromptRegistryTest extends TestCase
{
    public function test_get_returns_pinned_prompt_class(): void
    {
        $registry = new PromptRegistry(Container::getInstance(), [
            'add_references' => ['1.0.0' => AddReferencesV1::class],
        ]);

        $prompt = $registry->get('add_references', '1.0.0');

        self::assertInstanceOf(AddReferencesV1::class, $prompt);
        self::assertSame('add_references', AddReferencesV1::NAME);
        self::assertSame('1.0.0', AddReferencesV1::VERSION);
    }

    public function test_get_throws_for_unknown_pair(): void
    {
        $registry = new PromptRegistry(Container::getInstance(), [
            'add_references' => ['1.0.0' => AddReferencesV1::class],
        ]);

        $this->expectException(UnknownPromptException::class);

        $registry->get('add_references', '9.9.9');
    }

    public function test_get_throws_for_unknown_name(): void
    {
        $registry = new PromptRegistry(Container::getInstance(), []);

        $this->expectException(UnknownPromptException::class);

        $registry->get('does_not_exist', '1.0.0');
    }
}
