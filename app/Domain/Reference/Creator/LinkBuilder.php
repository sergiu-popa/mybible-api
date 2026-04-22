<?php

declare(strict_types=1);

namespace App\Domain\Reference\Creator;

interface LinkBuilder
{
    /**
     * Produce the `href` value for a matched reference.
     *
     * @param  string  $book  Canonical book abbreviation (e.g. `GEN`).
     * @param  string  $passage  Passage segment after the book (e.g. `1:1-3,5`
     *                           or `4:13;6:1-6` for composite references).
     * @param  string  $language  ISO-639-1 source language.
     */
    public function build(string $book, string $passage, string $language): string;
}
