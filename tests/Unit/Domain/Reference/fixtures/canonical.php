<?php

declare(strict_types=1);

use App\Domain\Reference\Reference;

return [
    'whole_chapter' => [
        'reference' => new Reference('GEN', 1, [], 'VDC'),
        'expected' => 'GEN.1.VDC',
    ],
    'single_verse' => [
        'reference' => new Reference('GEN', 1, [1], 'VDC'),
        'expected' => 'GEN.1:1.VDC',
    ],
    'verse_range' => [
        'reference' => new Reference('GEN', 1, [1, 2, 3], 'VDC'),
        'expected' => 'GEN.1:1-3.VDC',
    ],
    'mixed' => [
        'reference' => new Reference('GEN', 1, [1, 2, 3, 5, 7, 8, 9], 'VDC'),
        'expected' => 'GEN.1:1-3,5,7-9.VDC',
    ],
    'comma_only' => [
        'reference' => new Reference('GEN', 1, [1, 3, 5], 'VDC'),
        'expected' => 'GEN.1:1,3,5.VDC',
    ],
    'two_disjoint_ranges' => [
        'reference' => new Reference('JHN', 3, [16, 17, 18, 20, 21], 'VDC'),
        'expected' => 'JHN.3:16-18,20-21.VDC',
    ],
];
