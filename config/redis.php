<?php

return [
     /*
    | -------------
    | Redis Support
    | -------------
    |
    | Configure Redis details below to use it for caching and queueing.
    */
    'enable_redis' => env('ENABLE_REDIS', true),

    'redis_server' => env('REDIS_SERVER', '127.0.0.1'),

    'redis_port' => env('REDIS_PORT', 6379),

    'redis_index' => env('REDIS_INDEX', 1),

    'redis_pass' => env('REDIS_PASS'),

    'redis_socket' => env('REDIS_SOCKET', ''),
];