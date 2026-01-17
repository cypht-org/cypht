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
add_output('ajax_save_search', 'filter_saved_search_result', true);

add_handler('ajax_update_search', 'login', false, 'core');
add_handler('ajax_update_search', 'load_user_data', true, 'core');
add_handler('ajax_update_search', 'update_search', true, 'core');
add_handler('ajax_update_search', 'language', true);
add_handler('ajax_update_search', 'date', true, 'core');
add_handler('ajax_update_search', 'http_headers', true, 'core');
add_output('ajax_update_search', 'filter_saved_search_result', true);

add_handler('ajax_delete_search', 'login', false, 'core');
add_handler('ajax_delete_search', 'load_user_data', true, 'core');
add_handler('ajax_delete_search', 'delete_search', true, 'core');
add_handler('ajax_delete_search', 'language', true);
add_handler('ajax_delete_search', 'date', true, 'core');
add_handler('ajax_delete_search', 'http_headers', true, 'core');
add_output('ajax_delete_search', 'filter_saved_search_result', true);

add_handler('ajax_update_save_search_label', 'login', false, 'core');
add_handler('ajax_update_save_search_label', 'load_user_data', true, 'core');
add_handler('ajax_update_save_search_label', 'update_save_search_label', true, 'core');
add_handler('ajax_update_save_search_label', 'language', true);
add_handler('ajax_update_save_search_label', 'date', true, 'core');
add_handler('ajax_update_save_search_label', 'http_headers', true, 'core');
add_output('ajax_update_save_search_label', 'filter_saved_search_result', true);

add_handler('ajax_save_advanced_search', 'login', false, 'core');
add_handler('ajax_save_advanced_search', 'load_user_data', true, 'core');
add_handler('ajax_save_advanced_search', 'save_advanced_search', true, 'saved_searches');
add_handler('ajax_save_advanced_search', 'language', true);
add_handler('ajax_save_advanced_search', 'date', true, 'core');
add_handler('ajax_save_advanced_search', 'http_headers', true, 'core');
add_output('ajax_save_advanced_search', 'filter_advanced_search_result', true);

add_handler('ajax_load_advanced_search', 'login', false, 'core');
add_handler('ajax_load_advanced_search', 'load_user_data', true, 'core');
add_handler('ajax_load_advanced_search', 'load_advanced_search', true, 'saved_searches');
add_handler('ajax_load_advanced_search', 'language', true);
add_handler('ajax_load_advanced_search', 'date', true, 'core');
add_handler('ajax_load_advanced_search', 'http_headers', true, 'core');
add_output('ajax_load_advanced_search', 'filter_advanced_search_result', true);

add_handler('ajax_update_advanced_search', 'login', false, 'core');
add_handler('ajax_update_advanced_search', 'load_user_data', true, 'core');
add_handler('ajax_update_advanced_search', 'update_advanced_search', true, 'saved_searches');
add_handler('ajax_update_advanced_search', 'language', true);
add_handler('ajax_update_advanced_search', 'date', true, 'core');
add_handler('ajax_update_advanced_search', 'http_headers', true, 'core');
add_output('ajax_update_advanced_search', 'filter_advanced_search_result', true);

add_handler('ajax_delete_advanced_search', 'login', false, 'core');
add_handler('ajax_delete_advanced_search', 'load_user_data', true, 'core');
add_handler('ajax_delete_advanced_search', 'delete_advanced_search', true, 'saved_searches');
add_handler('ajax_delete_advanced_search', 'language', true);
add_handler('ajax_delete_advanced_search', 'date', true, 'core');
add_handler('ajax_delete_advanced_search', 'http_headers', true, 'core');
add_output('ajax_delete_advanced_search', 'filter_advanced_search_result', true);

add_handler('ajax_hm_folders', 'saved_search_folder_data',  true, 'saved_searches', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'search_folders',  true, 'saved_searches', 'folder_list_content_start', 'before');

add_handler('search', 'save_searches_data', true, 'saved_searches', 'load_user_data', 'after');
add_output('search', 'save_search_icon', true, 'saved_searches', 'search_results_table_end', 'after');
add_output('search', 'update_search_label_icon', true, 'saved_searches', 'search_results_table_end', 'after');
add_output('search', 'search_name_fld', true, 'saved_searches', 'search_form_content', 'after');
add_output('search', 'delete_search_icon', true, 'saved_searches', 'search_form_end', 'after');
add_output('search', 'update_search_icon', true, 'saved_searches', 'search_form_end', 'after');

add_handler('advanced_search', 'saved_search_folder_data', true, 'saved_searches', 'load_user_data', 'after');
add_handler('advanced_search', 'advanced_search_data', true, 'saved_searches', 'load_user_data', 'after');
add_output('advanced_search', 'advanced_search_data_handler', true, 'saved_searches', 'advanced_search_form_start', 'after');
add_output('advanced_search', 'advanced_search_save_icon', true, 'saved_searches', 'advanced_search_results_table_end', 'after');
add_output('advanced_search', 'advanced_search_update_icon', true, 'saved_searches', 'advanced_search_results_table_end', 'after');
add_output('advanced_search', 'advanced_search_delete_icon', true, 'saved_searches', 'advanced_search_update_icon', 'after');



return array(
    'allowed_pages' => array(
        'ajax_save_search',
        'ajax_update_search',
        'ajax_delete_search',
        'ajax_update_save_search_label',
        'ajax_save_advanced_search',
        'ajax_load_advanced_search',
        'ajax_update_advanced_search',
        'ajax_delete_advanced_search',
    ),
    'allowed_get' => array(
        'search_name' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        'search_terms' => FILTER_UNSAFE_RAW,
        'search_fld' => FILTER_UNSAFE_RAW,
        'search_since' => FILTER_UNSAFE_RAW,
    ),
    'allowed_post' => array(
        'search_name' => FILTER_UNSAFE_RAW,
        'search_terms' => FILTER_UNSAFE_RAW,
        'search_fld' => FILTER_UNSAFE_RAW,
        'search_since' => FILTER_UNSAFE_RAW,
        'search_terms_label' => FILTER_UNSAFE_RAW,
        'old_search_terms_label' => FILTER_UNSAFE_RAW,
        'adv_search_data' => FILTER_UNSAFE_RAW,
        'adv_terms' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_REQUIRE_ARRAY),
        'adv_targets' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_REQUIRE_ARRAY),
        'adv_source' => FILTER_UNSAFE_RAW,
        'adv_start' => FILTER_UNSAFE_RAW,
        'adv_end' => FILTER_UNSAFE_RAW,
        'adv_source_limit' => FILTER_VALIDATE_INT,
        'adv_charset' => FILTER_UNSAFE_RAW,
        'adv_flags' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_REQUIRE_ARRAY),
    ),
    'allowed_output' => array(
        'saved_search_result' => array(FILTER_VALIDATE_INT, false),
        'new_saved_search_label' => array(FILTER_UNSAFE_RAW, false),
        'advanced_search_result' => array(FILTER_VALIDATE_INT, false),
        'advanced_search_data' => array(FILTER_UNSAFE_RAW, false),
        'advanced_search_name' => array(FILTER_UNSAFE_RAW, false),
    ),
);
