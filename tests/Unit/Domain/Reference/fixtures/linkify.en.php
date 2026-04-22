<?php

declare(strict_types=1);

return [
    'single_simple' => [
        'input' => 'See John 3:16 for context.',
        'expected' => 'See <a class="js-read" href="JHN.3:16.KJV">John 3:16</a> for context.',
    ],
    'verse_range' => [
        'input' => 'Read Genesis 1:1-3 carefully.',
        'expected' => 'Read <a class="js-read" href="GEN.1:1-3.KJV">Genesis 1:1-3</a> carefully.',
    ],
    'comma_list' => [
        'input' => 'Compare Romans 3:23,24 with the rest.',
        'expected' => 'Compare <a class="js-read" href="ROM.3:23,24.KJV">Romans 3:23,24</a> with the rest.',
    ],
];
