<?php

declare(strict_types=1);

use App\Domain\Reference\Reference;

return [
    ['reference' => new Reference('GEN', 1, [], 'KJV'), 'expected' => 'Genesis 1'],
    ['reference' => new Reference('GEN', 1, [1], 'KJV'), 'expected' => 'Genesis 1:1'],
    ['reference' => new Reference('GEN', 1, [1, 2, 3], 'KJV'), 'expected' => 'Genesis 1:1-3'],
    ['reference' => new Reference('JHN', 3, [16], 'KJV'), 'expected' => 'John 3:16'],
    ['reference' => new Reference('PSA', 23, [], 'KJV'), 'expected' => 'Psalms 23'],
    ['reference' => new Reference('1CO', 13, [4, 5, 6, 7], 'KJV'), 'expected' => '1 Corinthians 13:4-7'],
    ['reference' => new Reference('REV', 22, [1, 2, 3, 5], 'KJV'), 'expected' => 'Revelation 22:1-3,5'],
    ['reference' => new Reference('SNG', 8, [6, 7], 'KJV'), 'expected' => 'Song of Songs 8:6-7'],
    ['reference' => new Reference('LAM', 3, [22, 23], 'KJV'), 'expected' => 'Lamentations 3:22-23'],
    ['reference' => new Reference('ZEP', 3, [17], 'KJV'), 'expected' => 'Zephaniah 3:17'],
    ['reference' => new Reference('1JN', 4, [7, 8], 'KJV'), 'expected' => '1 John 4:7-8'],
];
