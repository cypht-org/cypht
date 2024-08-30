<?php

return [
    /*
    | --------------------------------------------------------------------
    | Constants used for 2 factor authentication with Google Authenticator
    | --------------------------------------------------------------------
    |
    | SECURITY ALERT ! MAKE SURE THAT THIS FILE IS NOT ACCESSIBLE BY THE BROWSER !
    |
    | Enter the raw secret value (minimum 10 characters) to be used with the Google
    | Authenticator Application (or any TOTP app providing 6 digit pins). Users
    | must opt-in for 2fa on the site settings page which provides a QR barcode
    | to configure Google Authenticator.
    |
    | In order for 2fa to work, your server MUST have an accurate date and time,
    | otherwise the codes won't match up. NTP is the standard way to keep a server's
    | time synced: http://www.ntp.org/
    |
    */
    '2fa_secret' => env('APP_2FA_SECRET', ''),

    /*
    |
    | By default the generated secret will be 64 characters before being base32
    | encoded. To use a shorter secret that is easier to manually enter, set the
    | following to true. Note that if you change this setting after users have
    | enabled 2fa, they will have to use a backup code to login, then reset there
    | account in the authenticator app.
    */
    '2fa_simple' => env('APP_2FA_SIMPLE', false)
];
