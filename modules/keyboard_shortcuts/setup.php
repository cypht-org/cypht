<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('keyboard_shortcuts');
output_source('keyboard_shortcuts');

setup_base_page('shortcuts', 'core');
add_handler('shortcuts', 'load_edit_id', true, 'keyboard_shortcuts', 'load_keyboard_shortcuts', 'after');
add_handler('shortcuts', 'process_edit_shortcut', true, 'keyboard_shortcuts', 'load_edit_id', 'after');
add_output('shortcuts', 'start_shortcuts_page', true, 'keyboard_shortcuts', 'content_section_start', 'after');
add_output('shortcuts', 'shortcut_edit_form', true, 'keyboard_shortcuts', 'start_shortcuts_page', 'after');
add_output('shortcuts', 'shortcuts_content', true, 'keyboard_shortcuts', 'shortcut_edit_form', 'after');

add_handler('ajax_hm_folders', 'get_shortcut_setting', true, 'keyboard_shortcuts', 'load_user_data', 'after');
add_output('ajax_hm_folders', 'shortcuts_page_link', true, 'keyboard_shortcuts', 'settings_menu_end', 'before');

add_handler('settings', 'process_enable_shortcut_setting', true, 'keyboard_shortcuts', 'save_user_settings', 'before');
add_output('settings', 'enable_shortcut_setting', true, 'keyboard_shortcuts', 'start_general_settings', 'after');

add_module_to_all_pages('handler', 'load_keyboard_shortcuts', true, 'keyboard_shortcuts', 'load_user_data', 'after');
add_module_to_all_pages('output', 'keyboard_shortcut_data', true, 'keyboard_shortcuts', 'js_data', 'before');

return array(
    'allowed_pages' => array(
        'shortcuts'
    ),
    'allowed_output' => array(),
    'allowed_get' => array(
        'edit_id' => FILTER_VALIDATE_INT,
    ),
    'allowed_post' => array(
        'enable_keyboard_shortcuts' => FILTER_VALIDATE_INT,
        'shortcut_meta' => array('filter' => FILTER_SANITIZE_STRING, 'flags'  => FILTER_FORCE_ARRAY),
        'shortcut_key' => FILTER_VALIDATE_INT,
        'shortcut_id' => FILTER_VALIDATE_INT,
    )
);
