<?php

declare(strict_types=1);

return [
    'symfony_different_chapter' => [
        'input' => '<p>E heti tanulmányunk 1Mózes 9:15; 12:1-3.</p>',
        'expected' => '<p>E heti tanulmányunk <a class="js-read" href="GEN.9:15;12:1-3.KAR">1Mózes 9:15; 12:1-3</a>.</p>',
    ],
    'symfony_extra_space_last_verse' => [
        'input' => '<p>2Mózes 6:1-8; Ézsaiás 54:9; Jeremiás 31:33-34; Galata 3:6-9, 29</p>',
        'expected' => '<p><a class="js-read" href="EXO.6:1-8.KAR">2Mózes 6:1-8</a>; <a class="js-read" href="ISA.54:9.KAR">Ézsaiás 54:9</a>; <a class="js-read" href="JER.31:33-34.KAR">Jeremiás 31:33-34</a>; <a class="js-read" href="GAL.3:6-9,29.KAR">Galata 3:6-9, 29</a></p>',
    ],
    'symfony_inside_parenthesis' => [
        'input' => 'Meg a szövetségi ígéreteket (Gal 3:16; Zsid 6:13, 17). Szövetségi kötelesség Istennek a Tízparancsolatban kifejtett akarata iránti engedelmesség (5Móz 4:13). Az Istennel kötött szövetség kötelességének végeredményben Krisztus és a megváltási terv eszköze által lehet eleget tenni (Ézs 42:1, 6). Némelyek úgy képzelik, hogy Noé korában az özönvíz nem terjedt ki az egész világra, csak egy helyi áradás volt. Ha így lett volna, akkor minden árvíz (ilyesmi pedig állandóan történik) annak az ígéretnek a megszegését jelentené, ami 1Móz 9:15 versében hangzott el (lásd még Ézs 54:9).',
        'expected' => 'Meg a szövetségi ígéreteket (<a class="js-read" href="GAL.3:16.KAR">Gal 3:16</a>; <a class="js-read" href="HEB.6:13,17.KAR">Zsid 6:13, 17</a>). Szövetségi kötelesség Istennek a Tízparancsolatban kifejtett akarata iránti engedelmesség (<a class="js-read" href="DEU.4:13.KAR">5Móz 4:13</a>). Az Istennel kötött szövetség kötelességének végeredményben Krisztus és a megváltási terv eszköze által lehet eleget tenni (<a class="js-read" href="ISA.42:1,6.KAR">Ézs 42:1, 6</a>). Némelyek úgy képzelik, hogy Noé korában az özönvíz nem terjedt ki az egész világra, csak egy helyi áradás volt. Ha így lett volna, akkor minden árvíz (ilyesmi pedig állandóan történik) annak az ígéretnek a megszegését jelentené, ami <a class="js-read" href="GEN.9:15.KAR">1Móz 9:15</a> versében hangzott el (lásd még <a class="js-read" href="ISA.54:9.KAR">Ézs 54:9</a>).',
    ],
];
