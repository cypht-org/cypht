<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('themes');
output_source('themes');

add_module_to_all_pages('handler', 'load_theme', true, 'themes', 'load_user_data', 'after');
add_module_to_all_pages('output', 'theme_css', true, 'themes', 'header_css', 'after');

add_handler('ajax_hm_folders', 'load_theme', true, 'themes', 'load_user_data', 'after');
add_handler('settings', 'process_theme_setting', true, 'themes', 'save_user_settings', 'before'); 
add_output('settings', 'theme_setting', true, 'feeds', 'language_setting', 'after');

return array(
    'allowed_post' => array(
        'theme_setting' => FILTER_SANITIZE_STRING
    )
);

?>
