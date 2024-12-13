<?php
return [
    /*
    | -------------
    | Queue Support
    | -------------
    |
    | Configure Queue details below to use it for queueing.
    */
    'queue_enabled' => env('QUEUE_ENABLED', false),

    'queue_driver' => env('QUEUE_DRIVER', 'database'),
];