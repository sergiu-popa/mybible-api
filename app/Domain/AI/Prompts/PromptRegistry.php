<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompts;

use App\Domain\AI\Exceptions\UnknownPromptException;
use Illuminate\Contracts\Container\Container;

final class PromptRegistry
{
    /**
     * @param  array<string, array<string, class-string<Prompt>>>  $map  name => version => Prompt FQCN
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $map,
    ) {}

    public function get(string $name, string $version): Prompt
    {
        $class = $this->map[$name][$version] ?? null;

        if ($class === null) {
            throw UnknownPromptException::for($name, $version);
        }

        /** @var Prompt $instance */
        $instance = $this->container->make($class);

        return $instance;
    }
}
