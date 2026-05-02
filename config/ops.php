<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Internal Ops CIDR
    |--------------------------------------------------------------------------
    |
    | Comma-separated CIDR list of IP ranges allowed to access internal
    | operational endpoints like /ready. In production this should be set
    | to the LB/VPC CIDR. Local Docker dev: 127.0.0.1/32,172.16.0.0/12
    |
    */
    'internal_ops_cidr' => env('INTERNAL_OPS_CIDR', '10.114.0.0/20'),
];
