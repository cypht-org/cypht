<?php

return [
    /*
    | [wordpress.com]
    | ----------------------------------------------------------
    | Constants used for oauth2 communication with WordPress.com
    | ----------------------------------------------------------
    |
    | SECURITY ALERT ! MAKE SURE THAT THIS FILE IS NOT ACCESSIBLE BY THE BROWSER !
    */

    //[wordpress]
    'wordpress' => [
        'client_id'      => env('WORDPRESS_CLIENT_ID', ''),
        'client_secret'  => env('WORDPRESS_CLIENT_SECRET', ''),
        'client_uri'     => env('WORDPRESS_CLIENT_URI', '')
    ],
];
