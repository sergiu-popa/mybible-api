<?php

declare(strict_types=1);

return [

    'header' => 'X-Api-Key',

    'clients' => array_filter([
        'mobile' => env('API_KEY_MOBILE'),
        'admin' => env('API_KEY_ADMIN'),
        'frontend' => env('API_KEY_FRONTEND'),
    ]),

];
