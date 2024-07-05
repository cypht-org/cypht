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
add_output('ajax_hm_folders', 'tag_folders',  true, 'tags', 'folder_list_content_start', 'before');

// add_output('servers', 'add_tag_dialog', true, 'tags', 'server_content_end', 'before');
// add_output('servers', 'display_configured_tags', true, 'tags', 'add_tag_dialog', 'after');

add_handler('tags', 'tag_data', true, 'tags', 'load_user_data', 'after');
add_handler('tags', 'tag_edit_data', true, 'tags', 'tag_data', 'after');
add_handler('tags', 'process_tag_delete', true, 'tags', 'tag_data', 'after');
add_handler('tags', 'process_tag_update', true, 'tags', 'process_tag_delete', 'after');

setup_base_ajax_page('ajax_process_tag_update', 'tags');
add_handler('ajax_process_tag_update', 'process_tag_update',  true, 'tags');

add_output('ajax_imap_message_content', 'tag_bar',  true, 'tags', 'filter_message_headers', 'after');

return array(
    'allowed_pages' => array(
        'ajax_process_tag_update',
        'tags'
    ),
    'allowed_output' => array(
    ),
    'allowed_post' => array(
        'tag_name' => FILTER_DEFAULT,
        'tag_id' => FILTER_DEFAULT,
        'parent_tag' => FILTER_DEFAULT
    )
);
