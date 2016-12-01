<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('nasa');
output_source('nasa');

/* APOD display */
setup_base_page('nasa_apod', 'core');
add_handler('nasa_apod', 'fetch_apod_content', true, 'nasa', 'http_headers', 'after');
add_output('nasa_apod', 'apod_content', true, 'nasa', 'content_section_start', 'after');

/* folder list entry */
add_handler('ajax_hm_folders', 'nasa_folder_data',  true, 'nasa', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'nasa_folders',  true, 'nasa', 'folder_list_content_start', 'before');

/* servers page */
add_handler('servers', 'nasa_folder_data',  true, 'nasa', 'load_user_data', 'after');
add_output('servers', 'nasa_connect_section', true, 'nasa', 'server_content_end', 'before');

/* AJAX request to disconnect */
setup_base_ajax_page('ajax_nasa_disconnect', 'core');
add_handler('ajax_nasa_disconnect', 'process_nasa_connection', true, 'nasa', 'load_user_data', 'after');

/* AJAX request to connect */
setup_base_ajax_page('ajax_nasa_connect', 'core');
add_handler('ajax_nasa_connect', 'process_nasa_connection', true, 'nasa', 'load_user_data', 'after');

return array(
    'allowed_pages' => array(
        'nasa_apod',
        'ajax_nasa_connect',
        'ajax_nasa_disconnect',
    ),
    'allowed_post' => array(
        'api_key' => FILTER_SANITIZE_STRING,
        'nasa_disconnect' => FILTER_VALIDATE_BOOLEAN,
    ),
    'allowed_get' => array(
        'apod_date' => FILTER_SANITIZE_STRING,
    ),
    'allowed_output' => array(
        'nasa_action_status' => array(FILTER_VALIDATE_BOOLEAN, false),
    ),
);
