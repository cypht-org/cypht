<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('wordpress');
output_source('wordpress');

setup_base_page('wordpress', 'core');

add_output('ajax_hm_folders', 'wordpress_folders',  true, 'wordpress', 'folder_list_content_start', 'before');

add_handler('servers', 'setup_wordpress_connect', true, 'wordpress', 'load_user_data', 'after');
add_output('servers', 'wordpress_connect_section', true, 'wordpress', 'server_content_end', 'before');
add_handler('home', 'process_wordpress_authorization', true, 'wordpress', 'load_user_data', 'after');

return array(
    'allowed_pages' => array(
        'wordpress',
    )
);

?>
