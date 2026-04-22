<?php

declare(strict_types=1);

use App\Domain\Reference\Reference;

return [
    ['reference' => new Reference('GEN', 1, [], 'VDC'), 'expected' => 'Geneza 1'],
    ['reference' => new Reference('GEN', 1, [1], 'VDC'), 'expected' => 'Geneza 1:1'],
    ['reference' => new Reference('GEN', 1, [1, 2, 3], 'VDC'), 'expected' => 'Geneza 1:1-3'],
    ['reference' => new Reference('JHN', 3, [16], 'VDC'), 'expected' => 'Ioan 3:16'],
    ['reference' => new Reference('PSA', 23, [], 'VDC'), 'expected' => 'Psalmii 23'],
    ['reference' => new Reference('1CO', 13, [4, 5, 6, 7], 'VDC'), 'expected' => '1 Corinteni 13:4-7'],
    ['reference' => new Reference('REV', 22, [1, 2, 3, 5], 'VDC'), 'expected' => 'Apocalipsa 22:1-3,5'],
    ['reference' => new Reference('SNG', 8, [6, 7], 'VDC'), 'expected' => 'Cântarea Cântărilor 8:6-7'],
    ['reference' => new Reference('LAM', 3, [22, 23], 'VDC'), 'expected' => 'Plângerile lui Ieremia 3:22-23'],
    ['reference' => new Reference('ZEP', 3, [17], 'VDC'), 'expected' => 'Țefania 3:17'],
    ['reference' => new Reference('1JN', 4, [7, 8], 'VDC'), 'expected' => '1 Ioan 4:7-8'],
];
