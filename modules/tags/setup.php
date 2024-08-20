<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('tags');
output_source('tags');

setup_base_page('tags');
add_output('tags', 'tags_heading', true, 'core', 'content_section_start', 'after');
add_output('tags', 'tags_tree', true, 'core', 'tags_heading', 'after');
add_output('tags', 'tags_form', true, 'core', 'tags_tree', 'after');


add_module_to_all_pages('handler', 'mod_env', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'tag_data',  true, 'tags', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'tags',  true, 'tags', 'folder_list_content_start', 'before');

add_handler('ajax_imap_message_content', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('message_list', 'tag_data', true, 'tags', 'load_user_data', 'after');

setup_base_ajax_page('ajax_imap_tag_data', 'core');
add_handler('ajax_imap_tag_data', 'load_user_data', true,'core');
add_handler('ajax_imap_tag_data', 'load_imap_servers_from_config', true, 'imap', 'load_user_data', 'after');
add_handler('ajax_imap_tag_data', 'imap_tag_content', true, 'tags', 'load_imap_servers_from_config', 'after');
add_output('ajax_imap_tag_data', 'filter_tag_data', true);

add_handler('settings', 'process_tag_source_max_setting', true, 'tags', 'load_user_data', 'after');
add_handler('settings', 'process_tag_since_setting', true, 'tags', 'load_user_data', 'after');
add_output('settings', 'start_tag_settings', true, 'tags', 'sent_source_max_setting', 'after');
add_output('settings', 'tag_since_setting', true, 'tags', 'start_tag_settings', 'after');
add_output('settings', 'tag_per_source_setting', true, 'tags', 'tag_since_setting', 'after');

add_handler('tags', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('tags', 'tag_edit_data', true, 'tags', 'tag_data', 'after');
add_handler('tags', 'process_tag_delete', true, 'tags', 'tag_data', 'after');
add_handler('tags', 'process_tag_update', true, 'tags', 'process_tag_delete', 'after');

setup_base_ajax_page('ajax_process_tag_update', 'core');
add_handler('ajax_process_tag_update', 'process_tag_update',  true, 'tags');

add_output('ajax_imap_message_content', 'tag_bar',  true, 'tags', 'filter_message_headers', 'after');

return array(
    'allowed_pages' => array(
        'ajax_process_tag_update',
        'ajax_imap_tag_data',
        'tags'
    ),
    'allowed_output' => array(
    ),
    'allowed_post' => array(
        'tag_name' => FILTER_DEFAULT,
        'tag_id' => FILTER_DEFAULT,
        'parent_tag' => FILTER_DEFAULT,
        'tag_delete' => FILTER_DEFAULT,
        'tag_per_source' => FILTER_VALIDATE_INT,
        'tag_since' => FILTER_DEFAULT,
    ),
    'allowed_get' => array(
        'tag_id' => FILTER_DEFAULT,
    )
);
