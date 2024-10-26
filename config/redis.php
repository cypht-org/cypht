<?php

return [
    'redis_host' => env('REDIS_HOST',"127.0.0.1"),
    'redis_port' => env('REDIS_PORT',"6379"),
    'redis_username' => env('REDIS_USERNAME', null),
    'redis_password' => env('REDIS_PWD', null),
    'redis_prefix' => env('REDIS_PREFIX', ''),
];