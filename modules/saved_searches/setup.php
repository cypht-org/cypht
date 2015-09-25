<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('saved_searches');
output_source('saved_searches');

add_handler('ajax_save_search', 'login', false, 'core');
add_handler('ajax_save_search', 'load_user_data', true, 'core');
add_handler('ajax_save_search', 'save_search', true, 'core');
add_handler('ajax_save_search', 'language', true);
add_handler('ajax_save_search', 'date', true, 'core');
add_handler('ajax_save_search', 'http_headers', true, 'core');

add_handler('ajax_update_search', 'login', false, 'core');
add_handler('ajax_update_search', 'load_user_data', true, 'core');
add_handler('ajax_update_search', 'update_search', true, 'core');
add_handler('ajax_update_search', 'language', true);
add_handler('ajax_update_search', 'date', true, 'core');
add_handler('ajax_update_search', 'http_headers', true, 'core');

add_handler('ajax_delete_search', 'login', false, 'core');
add_handler('ajax_delete_search', 'load_user_data', true, 'core');
add_handler('ajax_delete_search', 'delete_search', true, 'core');
add_handler('ajax_delete_search', 'language', true);
add_handler('ajax_delete_search', 'date', true, 'core');
add_handler('ajax_delete_search', 'http_headers', true, 'core');


add_handler('ajax_hm_folders', 'saved_search_folder_data',  true, 'saved_searches', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'search_folders',  true, 'saved_searches', 'folder_list_content_start', 'before');

add_handler('search', 'save_searches_data', true, 'saved_searches', 'load_user_data', 'after');
add_output('search', 'save_searches_form', true, 'saved_searches', 'search_results_table_end', 'after');

return array(
    'allowed_pages' => array(
        'ajax_save_search',
        'ajax_update_search',
        'ajax_delete_search'
    ),
    'allowed_post' => array(
        'search_name' => FILTER_SANITIZE_STRING
    ),
);
