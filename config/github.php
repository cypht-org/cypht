<?php

return [
    /*
    | [github.com]
    | -------------------------------------------------------
    | Constants used for oauth2 communication with github.com
    | -------------------------------------------------------
    |
    | SECURITY ALERT ! MAKE SURE THAT THIS FILE IS NOT ACCESSIBLE BY THE BROWSER !
    */
    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID', ''),
        'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
        'redirect_uri'  => env('GITHUB_REDIRECT_URI', 'http://localhost/?page=home'),
        'auth_url'      => env('GITHUB_AUTH_URL', 'https://github.com/login/oauth/authorize'),
        'token_url'     => env('GITHUB_TOKEN_URL', 'https://github.com/login/oauth/access_token'),
    ],
];
