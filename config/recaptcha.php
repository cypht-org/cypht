<?php

return [
    /*
    | -----------------------------------
    | Constants used for google recaptcha
    | -----------------------------------
    |
    | SECURITY ALERT ! MAKE SURE THAT THIS FILE IS NOT ACCESSIBLE BY THE BROWSER !
    |
    */
    'recaptcha' => [
        /* Client secret for the recaptcha admin */
        'secret' => env('RECAPTCHA_SECRET', ''),

        /* Site key from the recaptcha admin */
        'site_key' => env('RECAPTCHA_SITE_KEY', '')
    ],
];
