<?php

if (!defined('DEBUG_MODE')) { die(); }

add_output('ajax_hm_folders', 'github_folders',  true, 'github', 'folder_list_content_start', 'before');

add_handler('message_list', 'github_list_type', true, 'github', 'message_list_type', 'after');

add_handler('servers', 'setup_github_connect', true, 'github', 'load_user_data', 'after');
add_output('servers', 'github_connect_section', true, 'github', 'server_content_end', 'before');

return array();

?>
