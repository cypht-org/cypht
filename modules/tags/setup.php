<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('tags');
output_source('tags');

add_module_to_all_pages('handler', 'mod_env', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'tag_data',  true, 'tags', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'tags',  true, 'tags', 'folder_list_content_start', 'before');

add_handler('ajax_imap_message_content', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('message_list', 'tag_data', true, 'tags', 'load_user_data', 'after');

setup_base_ajax_page('ajax_imap_tag_data', 'core');
add_handler('ajax_imap_tag_data', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_tag_data', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_imap_tag_data', 'imap_tag_content', true, 'tags', 'load_imap_servers_from_config', 'after');
add_output('ajax_imap_tag_data', 'filter_tag_data', true);

add_handler('settings', 'process_tag_source_max_setting', true, 'tags', 'load_user_data', 'after');
add_handler('settings', 'process_tag_since_setting', true, 'tags', 'load_user_data', 'after');
add_output('settings', 'start_tag_settings', true, 'tags', 'sent_source_max_setting', 'after');
add_output('settings', 'tag_since_setting', true, 'tags', 'start_tag_settings', 'after');
add_output('settings', 'tag_per_source_setting', true, 'tags', 'tag_since_setting', 'after');

setup_base_ajax_page('ajax_process_tag_update', 'core');
add_handler('ajax_process_tag_update', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_process_tag_update', 'process_tag_update', true, 'tags', 'tag_data', 'after');

setup_base_ajax_page('ajax_process_tag_delete', 'core');
add_handler('ajax_process_tag_delete', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_process_tag_delete', 'process_tag_delete', true, 'tags', 'tag_data', 'after');

add_output('ajax_imap_message_content', 'tag_bar',  true, 'tags', 'filter_message_headers', 'after');

/* add label email */
setup_base_ajax_page('ajax_imap_tag', 'tags');
add_handler('ajax_imap_tag', 'load_imap_servers_from_config',  true);
add_handler('ajax_imap_tag', 'save_imap_cache',  true, 'imap');
add_handler('ajax_imap_tag', 'close_session_early',  true, 'core');
add_handler('ajax_imap_tag', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_imap_tag', 'add_tag_to_message',  true, 'tags', 'save_imap_cache', 'after');
add_handler('ajax_imap_tag', 'remove_tag_from_message',  true, 'tags', 'save_imap_cache', 'after');

/* Sync the Tags Repository when moving messages */
add_handler('ajax_imap_move_copy_action', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_imap_move_copy_action', 'move_message', true, 'tags', 'imap_process_move', 'after');

return array(
    'allowed_pages' => array(
        'ajax_process_tag_update',
        'ajax_process_tag_delete',
        'ajax_imap_tag_data',
        'ajax_imap_tag',
    ),
    'allowed_output' => array(
        'tag_success' => array(FILTER_VALIDATE_BOOLEAN, false),
    ),
    'allowed_post' => array(
        'tag_name' => FILTER_UNSAFE_RAW,
        'tag_id' => FILTER_UNSAFE_RAW,
        'parent_tag' => FILTER_UNSAFE_RAW,
        'tag_color' => FILTER_UNSAFE_RAW,
        'tag_delete' => FILTER_UNSAFE_RAW,
        'tag_per_source' => FILTER_VALIDATE_INT,
        'tag_since' => FILTER_UNSAFE_RAW,
        'untag' => FILTER_VALIDATE_BOOLEAN,
        'tag' => FILTER_VALIDATE_BOOLEAN,
    ),
    'allowed_get' => array(
    )
);
