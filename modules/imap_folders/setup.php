<?php

/**
 * IMAP folder management modules
 * @package modules
 * @subpackage imap_folders/functions
 */

if (!defined('DEBUG_MODE')) { die(); }

handler_source('imap_folders');
output_source('imap_folders');

add_module_to_all_pages('handler', 'fix_folder_assignments', true, 'imap_folders', 'load_imap_servers_from_config', 'after');

setup_base_page('folders', 'core');
add_handler('folders', 'folders_server_id', true, 'imap_folders', 'load_user_data', 'after');
add_handler('folders', 'special_folders', true, 'imap_folders', 'folders_server_id', 'after');
add_output('folders', 'folders_content_start', true, 'imap_folders', 'version_upgrade_checker', 'after');
add_output('folders', 'folders_server_select', true, 'imap_folders', 'folders_folder_subscription_button', 'after');
add_output('folders', 'folders_create_dialog', true, 'imap_folders', 'folders_server_select', 'after');
add_output('folders', 'folders_rename_dialog', true, 'imap_folders', 'folders_create_dialog', 'after');
add_output('folders', 'folders_delete_dialog', true, 'imap_folders', 'folders_rename_dialog', 'after');
add_output('folders', 'folders_folder_subscription_button', true, 'imap_folders', 'folders_content_start', 'after');

add_handler('ajax_imap_folder_expand', 'add_folder_manage_link', true, 'imap_folders', 'imap_folder_expand', 'after');
add_handler('folders', 'get_only_subscribed_folders_setting', true, 'imap_folders');

// Commented out during development
add_output('folders', 'folders_trash_dialog', true, 'imap_folders', 'folders_delete_dialog', 'after');
add_output('folders', 'folders_sent_dialog', true, 'imap_folders', 'folders_trash_dialog', 'after');
add_output('folders', 'folders_archive_dialog', true, 'imap_folders', 'folders_sent_dialog', 'after');
add_output('folders', 'folders_draft_dialog', true, 'imap_folders', 'folders_archive_dialog', 'after');
add_output('folders', 'folders_junk_dialog', true, 'imap_folders', 'folders_draft_dialog', 'after');
add_output('folders', 'folders_folder_subscription', true, 'imap_folders', 'folders_server_select', 'after');

add_handler('compose', 'special_folders', true, 'imap_folders', 'load_user_data', 'after');

setup_base_ajax_page('ajax_imap_folders_delete', 'core');
add_handler('ajax_imap_folders_delete', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_folders_delete', 'process_folder_delete', true, 'imap_folders', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_folders_delete', 'imap_bust_cache', true, 'imap', 'process_folder_delete', 'after');
add_handler('ajax_imap_folders_delete', 'close_session_early', true, 'core', 'imap_bust_cache', 'after');

setup_base_ajax_page('ajax_imap_folders_rename', 'core');
add_handler('ajax_imap_folders_rename', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_folders_rename', 'process_folder_rename', true, 'imap_folders', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_folders_rename', 'imap_bust_cache',  true, 'imap', 'process_folder_rename', 'after');
add_handler('ajax_imap_folders_rename', 'close_session_early', true, 'core', 'imap_bust_cache', 'after');

setup_base_ajax_page('ajax_imap_folders_create', 'core');
add_handler('ajax_imap_folders_create', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_folders_create', 'process_folder_create', true, 'imap_folders', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_folders_create', 'imap_bust_cache', true, 'imap', 'process_folder_create', 'after');
add_handler('ajax_imap_folders_create', 'close_session_early', true, 'core', 'imap_bust_cache', 'after');

setup_base_ajax_page('ajax_imap_clear_special_folder', 'core');
add_handler('ajax_imap_clear_special_folder', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_clear_special_folder', 'process_clear_special_folder', true, 'imap_folders', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_clear_special_folder', 'save_user_data', true, 'core', 'process_clear_special_folder', 'after');

setup_base_ajax_page('ajax_imap_special_folder', 'core');
add_handler('ajax_imap_special_folder', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_special_folder', 'process_special_folder', true, 'imap_folders', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_special_folder', 'save_user_data', true, 'core', 'process_special_folder', 'after');

setup_base_ajax_page('ajax_imap_accept_special_folders', 'core');
add_handler('ajax_imap_accept_special_folders', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_accept_special_folders', 'process_accept_special_folders', true, 'imap_folders', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_accept_special_folders', 'save_user_data', true, 'core', 'process_special_folders', 'after');

add_handler('ajax_hm_folders', 'imap_folder_check', true, 'imap_folders', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'folders_page_link', true, 'imap_folders', 'settings_menu_end', 'before');

add_handler('settings', 'process_only_subscribed_folders_setting', true, 'imap', 'date', 'after');
add_output('settings', 'imap_only_subscribed_folders_setting', true, 'imap', 'original_folder_setting', 'after');

setup_base_page('folders_subscription', 'core');
add_handler('folders_subscription', 'folders_server_id', true, 'imap_folders', 'load_user_data', 'after');
add_handler('folders_subscription', 'special_folders', true, 'imap_folders', 'folders_server_id', 'after');
add_handler('folders_subscription', 'get_only_subscribed_folders_setting', true, 'imap_folders');
add_output('folders_subscription', 'folders_subscription_content_start', true, 'imap_folders', 'version_upgrade_checker', 'after');
add_output('folders_subscription', 'folders_server_select', true, 'imap_folders', 'folders_subscription_content_start', 'after');
add_output('folders_subscription', 'folders_folder_subscription', true, 'imap_folders', 'folders_server_select', 'after');

setup_base_ajax_page('ajax_imap_folder_subscription', 'core');
add_handler('ajax_imap_folder_subscription', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_folder_subscription', 'process_imap_folder_subscription', true, 'imap_folders', 'load_imap_servers_from_config', 'after');
add_handler('ajax_imap_folder_subscription', 'save_user_data', true, 'core', 'process_special_folder', 'after');

return array(
    'allowed_pages' => array(
        'folders',
        'folders_subscription',
        'ajax_imap_folders_delete',
        'ajax_imap_folders_create',
        'ajax_imap_folders_rename',
        'ajax_imap_special_folder',
        'ajax_imap_clear_special_folder',
        'ajax_imap_accept_special_folders',
        'ajax_imap_folder_subscription'
    ),
    'allowed_output' => array(
        'imap_folders_success' => array(FILTER_VALIDATE_INT, false),
        'imap_special_name' => array(FILTER_UNSAFE_RAW, false),
        'imap_folder_subscription' => array(FILTER_UNSAFE_RAW, false),
    ),
    'allowed_get' => array(),
    'allowed_post' => array(
        'parent' => FILTER_UNSAFE_RAW,
        'new_folder' => FILTER_UNSAFE_RAW,
        'special_folder_type' => FILTER_UNSAFE_RAW,
        'imap_service_name' => FILTER_UNSAFE_RAW,
        'subscription_state' => FILTER_VALIDATE_BOOLEAN,
        'folder' => FILTER_UNSAFE_RAW,
        'only_subscribed_folders' => FILTER_VALIDATE_BOOLEAN,
    )
);
