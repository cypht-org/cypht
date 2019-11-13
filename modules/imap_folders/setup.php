<?php

/**
 * IMAP folder management modules
 * @package modules
 * @subpackage imap_folders/functions
 */

if (!defined('DEBUG_MODE')) { die(); }

handler_source('imap_folders');
output_source('imap_folders');

setup_base_page('folders', 'core');
add_handler('folders', 'folders_server_id', true, 'imap_folders', 'load_user_data', 'after');
add_handler('folders', 'special_folders', true, 'imap_folders', 'folders_server_id', 'after');
add_output('folders', 'folders_content_start', true, 'imap_folders', 'content_section_start', 'after');
add_output('folders', 'folders_server_select', true, 'imap_folders', 'folders_content_start', 'after');
add_output('folders', 'folders_create_dialog', true, 'imap_folders', 'folders_server_select', 'after');
add_output('folders', 'folders_rename_dialog', true, 'imap_folders', 'folders_create_dialog', 'after');
add_output('folders', 'folders_delete_dialog', true, 'imap_folders', 'folders_rename_dialog', 'after');

add_handler('ajax_imap_folder_expand', 'add_folder_manage_link', true, 'imap_folders', 'imap_folder_expand', 'after');

// Commented out during development
add_output('folders', 'folders_trash_dialog', true, 'imap_folders', 'folders_delete_dialog', 'after');
add_output('folders', 'folders_sent_dialog', true, 'imap_folders', 'folders_trash_dialog', 'after');
add_output('folders', 'folders_archive_dialog', true, 'imap_folders', 'folders_sent_dialog', 'after');
//add_output('folders', 'folders_draft_dialog', true, 'imap_folders', 'folders_sent_dialog', 'after');

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

add_handler('ajax_hm_folders', 'imap_folder_check', true, 'imap_folders', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'folders_page_link', true, 'imap_folders', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'folders',
        'ajax_imap_folders_delete',
        'ajax_imap_folders_create',
        'ajax_imap_folders_rename',
        'ajax_imap_special_folder',
        'ajax_imap_clear_special_folder'
    ),
    'allowed_output' => array(
        'imap_folders_success' => array(FILTER_VALIDATE_INT, false),
        'imap_special_name' => array(FILTER_SANITIZE_STRING, false)
    ),
    'allowed_get' => array(),
    'allowed_post' => array(
        'parent' => FILTER_SANITIZE_STRING,
        'new_folder' => FILTER_SANITIZE_STRING,
        'special_folder_type' => FILTER_SANITIZE_STRING
    )
);
