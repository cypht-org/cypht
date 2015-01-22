<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('wordpress');
output_source('wordpress');

setup_base_page('wordpress', 'core');

add_output('ajax_hm_folders', 'wordpress_folders',  true, 'wordpress', 'folder_list_content_start', 'before');

return array(
    'allowed_pages' => array(
        'wordpress',
    )
);

?>
