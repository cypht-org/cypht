<?php

/* homepage data */
Hm_Handler_Modules::add('home', 'login', false);
Hm_Handler_Modules::add('home', 'load_user_data', true);
Hm_Handler_Modules::add('home', 'language',  true);
Hm_Handler_Modules::add('home', 'title', true);
Hm_Handler_Modules::add('home', 'date', true);
Hm_Handler_Modules::add('home', 'save_user_data', true);
Hm_Handler_Modules::add('home', 'logout', true);
Hm_Handler_Modules::add('home', 'http_headers', true);

/* homepage output */
Hm_Output_Modules::add('home', 'header', false);
Hm_Output_Modules::add('home', 'logout', true);
Hm_Output_Modules::add('home', 'login', false);
Hm_Output_Modules::add('home', 'title', true);
Hm_Output_Modules::add('home', 'msgs', false);
Hm_Output_Modules::add('home', 'date', true);
Hm_Output_Modules::add('home', 'footer', true);

/* not-found page data and output */
Hm_Handler_Modules::add('notfound', 'title', true);
Hm_Output_Modules::add('notfound', 'title', true);


/* allowed input */
return array(
    'allowed_pages' => array(
        'home',
        'notfound'
    ),
    'allowed_cookie' => array(
        'PHPSESSID' => FILTER_SANITIZE_STRING,
        'hm_id' => FILTER_SANITIZE_STRING,
        'hm_session' => FILTER_SANITIZE_STRING
    ),
    'allowed_server' => array(
        'REQUEST_URI' => FILTER_SANITIZE_STRING,
        'SERVER_ADDR' => FILTER_VALIDATE_IP,
        'SERVER_PORT' => FILTER_VALIDATE_INT,
        'PHP_SELF' => FILTER_SANITIZE_STRING,
        'HTTP_USER_AGENT' => FILTER_SANITIZE_STRING,
        'SERVER_NAME' => FILTER_SANITIZE_STRING
    ),

    'allowed_get' => array(
        'page' => FILTER_SANITIZE_STRING,
    ),

    'allowed_post' => array(
        'logout' => FILTER_VALIDATE_BOOLEAN,
        'username' => FILTER_SANITIZE_STRING,
        'password' => FILTER_SANITIZE_STRING,
        'hm_ajax_hook' => FILTER_SANITIZE_STRING,
    )
);

?>
