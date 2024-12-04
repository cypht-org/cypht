<?php

return [
    /*
    | -------------------------------------------------
    | Constants used for oauth2 authentication over IMAP
    | -------------------------------------------------
    |
    | Currently there are only two popular E-mail providers supporting IMAP/oauth2,
    | Outlook and Gmail. In order to use oauth2 you must create a web application
    | that generates a client id, client secret and a redirect URI, then define them
    | in this file.
    |
    | An OAuth2 app can connect multiple accounts by using the user's authorization
    | to request unique access tokens for each account during the OAuth2 flow.
    | Each token corresponds to a specific user's permissions and account data.
    |
    | Outlook.com https://account.live.com/developers/applications/
    | Gmail: https://console.developers.google.com/project
    |
    */

    //[gmail]
    'gmail' => [
        'client_id'      => env('GMAIL_CLIENT_ID', ''),
        'client_secret'  => env('GMAIL_CLIENT_SECRET', ''),
        'client_uri'     => env('GMAIL_CLIENT_URI', 'http://localhost/?page=home'),
        'auth_uri'       => env('GMAIL_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
        'token_uri'      => env('GMAIL_TOKEN_URI', 'https://www.googleapis.com/oauth2/v3/token'),
        'refresh_uri'    => env('GMAIL_REFRESH_URI', 'https://www.googleapis.com/oauth2/v3/token')
    ],

    //[outlook]
    'outlook' => [
        'client_id'      => env('OUTLOOK_CLIENT_ID', ''),
        'client_secret'  => env('OUTLOOK_CLIENT_SECRET', ''),
        'client_uri'     => env('OUTLOOK_CLIENT_URI', 'http://localhost/?page=home'),
        'auth_uri'       => env('OUTLOOK_AUTH_URI', 'https://login.live.com/oauth20_authorize.srf'),
        'token_uri'      => env('OUTLOOK_TOKEN_URI', 'https://login.live.com/oauth20_token.srf'),
        'refresh_uri'    => env('OUTLOOK_REFRESH_URI', 'https://login.live.com/oauth20_token.srf')
    ],

    //[office365]
    'office365' => [
        'client_id'      => env('OFFICE365_CLIENT_ID', ''),
        'client_secret'  => env('OFFICE365_CLIENT_SECRET', ''),
        'client_uri'     => env('OFFICE365_CLIENT_URI', 'http://localhost/?page=home'),
        'auth_uri'       => env('OFFICE365_AUTH_URI', 'https://login.live.com/oauth20_authorize.srf'),
        'token_uri'      => env('OFFICE365_TOKEN_URI', 'https://login.live.com/oauth20_token.srf'),
        'refresh_uri'    => env('OFFICE365_REFRESH_URI', 'https://login.live.com/oauth20_token.srf')
    ],
];
