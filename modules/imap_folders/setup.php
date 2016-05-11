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
add_output('folders', 'folders_content_start', true, 'imap_folders', 'content_section_start', 'after');
add_output('folders', 'folders_server_select', true, 'imap_folders', 'folders_content_start', 'after');
add_output('folders', 'folders_create_dialog', true, 'imap_folders', 'folders_server_select', 'after');
add_output('folders', 'folders_rename_dialog', true, 'imap_folders', 'folders_create_dialog', 'after');
add_output('folders', 'folders_delete_dialog', true, 'imap_folders', 'folders_rename_dialog', 'after');

setup_base_ajax_page('ajax_imap_folders_delete', 'core');
add_handler('ajax_imap_folders_delete', 'process_folder_delete', true, 'imap_folders', 'load_user_data', 'after');

setup_base_ajax_page('ajax_imap_folders_rename', 'core');
add_handler('ajax_imap_folders_rename', 'process_folder_rename', true, 'imap_folders', 'load_user_data', 'after');

setup_base_ajax_page('ajax_imap_folders_create', 'core');
add_handler('ajax_imap_folders_create', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_folders_create', 'process_folder_create', true, 'imap_folders', 'load_imap_servers_from_config', 'after');

add_handler('ajax_hm_folders', 'imap_folder_check', true, 'imap_folders', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'folders_page_link', true, 'imap_folders', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'folders',
        'ajax_imap_folders_delete',
        'ajax_imap_folders_create',
        'ajax_imap_folders_rename'
    ),
    'allowed_output' => array(),
    'allowed_get' => array(),
    'allowed_post' => array(
        'parent' => FILTER_SANITIZE_STRING,
        'new_folder' => FILTER_SANITIZE_STRING,
    )
);
