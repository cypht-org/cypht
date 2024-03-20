<?php

return [
    /* 
    | 
    | ----------------------------------------
    | Constants used for CardDav communication
    | ----------------------------------------
    |
    | Once you edit this file, you must move it to the directory defined by
    | app_data_dir in your config/app.php file. No need to re-run the
    | config_gen.php script.
    | 
    | SECURITY ALERT ! MAKE SURE THAT THIS FILE IS NOT ACCESSIBLE BY THE BROWSER !
    | 
    | Create one section for each CardDav backend you want to support. The section
    | name will be used in the UI for the name of this addressbook
    | 
    | 
    */
    'Personal' => [
        'server' => env('CARD_DAV_SERVER', 'http://localhost:5232'),
    ]
];
