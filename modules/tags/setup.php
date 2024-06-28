<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('tags');
output_source('tags');

add_module_to_all_pages('handler', 'mod_env', true, 'tags', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'tag_data',  true, 'tags', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'tag_folders',  true, 'tags', 'folder_list_content_start', 'before');

add_output('servers', 'add_tag_dialog', true, 'tags', 'server_content_end', 'before');
add_output('servers', 'display_configured_tags', true, 'tags', 'add_tag_dialog', 'after');


add_output('ajax_imap_message_content', 'tag_bar',  true, 'tags', 'filter_message_headers', 'after');

return array(
    'allowed_pages' => array(
    ),
    'allowed_output' => array(
    ),
    'allowed_post' => array(
    )
);
