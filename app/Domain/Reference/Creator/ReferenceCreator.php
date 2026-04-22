<?php

declare(strict_types=1);

namespace App\Domain\Reference\Creator;

use App\Domain\Reference\Formatter\ReferenceFormatter;

final class ReferenceCreator
{
    private readonly ReferenceFormatter $formatter;

    private readonly LinkBuilder $linkBuilder;

    public function __construct(
        ?LinkBuilder $linkBuilder = null,
        ?ReferenceFormatter $formatter = null,
    ) {
        $this->formatter = $formatter ?? new ReferenceFormatter;
        $this->linkBuilder = $linkBuilder ?? new CanonicalLinkBuilder($this->formatter);
    }

    /**
     * Wrap each Bible reference matched by the language regex in a
     * `<a class="js-read" href="…">…</a>` anchor.
     *
     * The href is whatever the configured {@see LinkBuilder} produces
     * (canonical query by default, real URL when wired from HTTP).
     */
    public function linkify(string $text, string $language): string
    {
        $formatter = $this->formatter->forLanguage($language);
        $regex = $formatter->linkifyRegex();

        $result = preg_replace_callback(
            $regex,
            function (array $matches) use ($formatter, $language): string {
                $original = $matches[0];
                $book = $matches[1];
                $passage = trim(str_replace(' ', '', $matches[2]), ';:,');
                $abbrev = $formatter->abbreviation($book);
                $href = $this->linkBuilder->build($abbrev, $passage, $language);

                return sprintf('<a class="js-read" href="%s">%s</a>', $href, $original);
            },
            $text,
        );

        return $result ?? $text;
    }
}
