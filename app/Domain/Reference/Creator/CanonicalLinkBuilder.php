<?php

declare(strict_types=1);

namespace App\Domain\Reference\Creator;

use App\Domain\Reference\Formatter\ReferenceFormatter;

final class CanonicalLinkBuilder implements LinkBuilder
{
    public function __construct(
        private readonly ReferenceFormatter $formatter = new ReferenceFormatter,
    ) {}

    public function build(string $book, string $passage, string $language): string
    {
        return sprintf('%s.%s.%s', $book, $passage, $this->formatter->forLanguage($language)->defaultVersion());
    }
}
