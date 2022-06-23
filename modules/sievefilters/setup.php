<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('sievefilters');
output_source('sievefilters');

setup_base_page('sievefilters', 'core');

add_output('sievefilters', 'sievefilters_settings_start', true, 'sievefilters', 'content_section_start', 'after');
add_output('sievefilters', 'sievefilters_settings_accounts', true, 'sievefilters', 'sievefilters_settings_start', 'after');
add_handler('sievefilters', 'settings_load_imap', true, 'sievefilters', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'sievefilters_settings_link', true, 'sievefilters', 'settings_menu_end', 'before');

/* save filter */
setup_base_ajax_page('ajax_sieve_save_filter', 'core');
add_handler('ajax_sieve_save_filter', 'settings_load_imap',  true);
add_handler('ajax_sieve_save_filter', 'sieve_save_filter',  true);
add_output('ajax_sieve_save_filter', 'sieve_save_filter_output',  true);

/* edit filter */
setup_base_ajax_page('ajax_sieve_edit_filter', 'core');
add_handler('ajax_sieve_edit_filter', 'settings_load_imap',  true);
add_handler('ajax_sieve_edit_filter', 'sieve_edit_filter',  true);
add_output('ajax_sieve_edit_filter', 'sieve_edit_filter_output',  true);

/* delete filter */
setup_base_ajax_page('ajax_sieve_delete_filter', 'core');
add_handler('ajax_sieve_delete_filter', 'settings_load_imap',  true);
add_handler('ajax_sieve_delete_filter', 'sieve_delete_filter',  true);
add_output('ajax_sieve_delete_filter', 'sieve_delete_output',  true);

/* save script */
setup_base_ajax_page('ajax_sieve_save_script', 'core');
add_handler('ajax_sieve_save_script', 'settings_load_imap',  true);
add_handler('ajax_sieve_save_script', 'sieve_save_script',  true);
add_output('ajax_sieve_save_script', 'sieve_save_script_output',  true);

/* edit script */
setup_base_ajax_page('ajax_sieve_edit_script', 'core');
add_handler('ajax_sieve_edit_script', 'settings_load_imap',  true);
add_handler('ajax_sieve_edit_script', 'sieve_edit_script',  true);
add_output('ajax_sieve_edit_script', 'sieve_edit_script_output',  true);

/* delete script */
setup_base_ajax_page('ajax_sieve_delete_script', 'core');
add_handler('ajax_sieve_delete_script', 'settings_load_imap',  true);
add_handler('ajax_sieve_delete_script', 'sieve_delete_script',  true);
add_output('ajax_sieve_delete_script', 'sieve_delete_output',  true);


return array(
    'allowed_pages' => array(
        'sievefilters',
        'ajax_sieve_save_script',
        'ajax_sieve_edit_script',
        'ajax_sieve_delete_script',
        'ajax_sieve_save_filter',
        'ajax_sieve_edit_filter',
        'ajax_sieve_delete_filter'
    ),
    'allowed_output' => array(
        'imap_server_ids' => array(FILTER_UNSAFE_RAW, false),
        'script_removed' => array(FILTER_UNSAFE_RAW, false),
        'script' => array(FILTER_UNSAFE_RAW, false),
        'conditions' => array(FILTER_UNSAFE_RAW, false),
        'actions' => array(FILTER_UNSAFE_RAW, false),
        'test_type' => array(FILTER_UNSAFE_RAW, false)
    ),
    'allowed_get' => array(),
    'allowed_post' => array(
        'imap_account' => FILTER_SANITIZE_STRING,
        'sieve_script_name' => FILTER_SANITIZE_STRING,
        'sieve_script_priority' => FILTER_VALIDATE_INT,
        'sieve_filter_name' => FILTER_SANITIZE_STRING,
        'sieve_filter_priority' => FILTER_VALIDATE_INT,
        'script' => FILTER_UNSAFE_RAW,
        'current_editing_script' => FILTER_SANITIZE_STRING,
        'current_editing_filter_name' => FILTER_SANITIZE_STRING,
        'conditions_json' => FILTER_UNSAFE_RAW,
        'actions_json' => FILTER_UNSAFE_RAW,
        'filter_test_type' => FILTER_SANITIZE_STRING
    )
);
