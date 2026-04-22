<?php

declare(strict_types=1);

return [
    'symfony_complex_paragraph' => [
        'input' => '<p>Testing (Geneza 17:2).</p><p><ol><li>Dumnezeu a confirmat făgăduințele (Galateni 3:16; Evrei 6:13,17). </li><li>Condiția Cele Zece Porunci (Deuteronomul 4:13; 6:1-6). </li><li>Mijlocul prin care condiția (Țefania 42:1-6, 9).</li></ol>',
        'expected' => '<p>Testing (<a class="js-read" href="GEN.17:2.VDC">Geneza 17:2</a>).</p><p><ol><li>Dumnezeu a confirmat făgăduințele (<a class="js-read" href="GAL.3:16.VDC">Galateni 3:16</a>; <a class="js-read" href="HEB.6:13,17.VDC">Evrei 6:13,17</a>). </li><li>Condiția Cele Zece Porunci (<a class="js-read" href="DEU.4:13;6:1-6.VDC">Deuteronomul 4:13; 6:1-6</a>). </li><li>Mijlocul prin care condiția (<a class="js-read" href="ZEP.42:1-6,9.VDC">Țefania 42:1-6, 9</a>).</li></ol>',
    ],
    'single_simple' => [
        'input' => 'Vezi Ioan 3:16.',
        'expected' => 'Vezi <a class="js-read" href="JHN.3:16.VDC">Ioan 3:16</a>.',
    ],
];
