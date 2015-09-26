<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('idle_timer');
output_source('idle_timer');


/* settings page */
add_handler('settings', 'process_idle_time_setting', true, 'idle_timer', 'date', 'after');
add_output('settings', 'idle_time_setting', true, 'idle_timer', 'list_style_setting', 'after');
add_module_to_all_pages('handler', 'idle_time_check', true, 'idle_timer', 'load_user_data', 'after');

/* no-op poll */
add_handler('ajax_no_op', 'login', false, 'core');
add_handler('ajax_no_op', 'load_user_data', true, 'core');
add_handler('ajax_no_op', 'process_idle_time', true);
add_handler('ajax_no_op', 'date', true, 'core');
add_handler('ajax_no_op', 'http_headers', true, 'core');

return array(
    'allowed_pages' => array(
        'ajax_no_op'
    ),
    'allowed_post' => array(
        'idle_time' => FILTER_VALIDATE_INT
    )
);


