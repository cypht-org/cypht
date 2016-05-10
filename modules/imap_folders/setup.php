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

add_handler('ajax_hm_folders', 'imap_folder_check', true, 'imap_folders', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'folders_page_link', true, 'imap_folders', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'folders',
    ),
    'allowed_output' => array(),
    'allowed_get' => array(),
    'allowed_post' => array()
);
