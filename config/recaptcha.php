<?php

return [
    /*
    | -----------------------------------
    | Constants used for google recaptcha
    | -----------------------------------
    |
    | Once you edit this file, you must move it to the directory defined by
    | app_data_dir in your config/app.php file. No need to re-run the
    | config_gen.php script.
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
