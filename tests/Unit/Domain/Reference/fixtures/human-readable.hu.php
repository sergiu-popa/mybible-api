<?php

declare(strict_types=1);

use App\Domain\Reference\Reference;

return [
    ['reference' => new Reference('GEN', 1, [], 'KAR'), 'expected' => '1Mózes 1'],
    ['reference' => new Reference('GEN', 1, [1], 'KAR'), 'expected' => '1Mózes 1:1'],
    ['reference' => new Reference('GEN', 1, [1, 2, 3], 'KAR'), 'expected' => '1Mózes 1:1-3'],
    ['reference' => new Reference('JHN', 3, [16], 'KAR'), 'expected' => 'János 3:16'],
    ['reference' => new Reference('PSA', 23, [], 'KAR'), 'expected' => 'Zsoltárok 23'],
    ['reference' => new Reference('1CO', 13, [4, 5, 6, 7], 'KAR'), 'expected' => '1Korintus 13:4-7'],
    ['reference' => new Reference('REV', 22, [1, 2, 3, 5], 'KAR'), 'expected' => 'Jelenések 22:1-3,5'],
    ['reference' => new Reference('SNG', 8, [6, 7], 'KAR'), 'expected' => 'Énekek éneke 8:6-7'],
    ['reference' => new Reference('LAM', 3, [22, 23], 'KAR'), 'expected' => 'Jeremiás siralmai 3:22-23'],
    ['reference' => new Reference('HEB', 11, [1], 'KAR'), 'expected' => 'Zsidó 11:1'],
    ['reference' => new Reference('1JN', 4, [7, 8], 'KAR'), 'expected' => '1János 4:7-8'],
];
