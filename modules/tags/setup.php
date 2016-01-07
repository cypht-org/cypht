<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('tags');
output_source('tags');

add_handler('ajax_hm_folders', 'tag_data',  true, 'tags', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'tag_folders',  true, 'tags', 'folder_list_content_start', 'before');

return array();
