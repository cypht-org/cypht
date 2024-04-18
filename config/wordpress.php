<?php

return [
    /*
    | [wordpress.com]
    | ----------------------------------------------------------
    | Constants used for oauth2 communication with WordPress.com
    | ----------------------------------------------------------
    |
    | Once you edit this file, you must move it to the directory defined by
    | app_data_dir in your config/app.php file. No need to re-run the
    | config_gen.php script.
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
