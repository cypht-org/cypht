<?php

if (!defined('DEBUG_MODE')) { die(); }

add_handler('ajax_hm_folders', 'github_folders_data',  true, 'github', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'github_folders',  true, 'github', 'folder_list_content_start', 'before');

add_handler('message_list', 'github_list_type', true, 'github', 'message_list_type', 'after');

add_handler('servers', 'setup_github_connect', true, 'github', 'load_user_data', 'after');
add_handler('servers', 'github_disconnect', true, 'github', 'setup_github_connect', 'after');
add_output('servers', 'github_connect_section', true, 'github', 'server_content_end', 'before');

add_handler('home', 'process_github_authorization', true, 'github', 'load_user_data', 'after');

return array(
    'allowed_pages' => array(
    ),
    'allowed_post' => array(
        'github_disconnect' => FILTER_SANITIZE_STRING
    )
);

?>
