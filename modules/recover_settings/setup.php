<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('recover_settings');
output_source('recover_settings');

setup_base_page('recover_settings', 'core');
add_handler('recover_settings', 'reload_folder_cookie', true, 'core', 'save_user_data', 'after');
add_handler('recover_settings', 'process_recover_settings_form', true, 'recover_settings', 'load_user_data', 'after');
add_output('recover_settings', 'recover_settings_page', true, 'recover_settings', 'content_section_start', 'after');

add_module_to_all_pages('handler', 'check_for_lost_settings', true, 'recover_settings', 'load_user_data', 'after');
add_handler('ajax_hm_folders', 'check_for_lost_settings', true, 'recover_settings', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'recover_settings_page_link', true, 'recover_settings', 'main_menu_start', 'after');

return array(
    'allowed_pages' => array('recover_settings'),
    'allowed_post' => array(
        'old_password_recover' => FILTER_UNSAFE_RAW,
        'new_password_recover' => FILTER_UNSAFE_RAW,
        'recover_settings' => FILTER_SANITIZE_STRING
    )
);
