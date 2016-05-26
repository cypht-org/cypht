<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('keyboard_shortcuts');
output_source('keyboard_shortcuts');

setup_base_page('shortcuts', 'core');
add_output('shortcuts', 'start_shortcuts_page', true, 'keyboard_shortcuts', 'content_section_start', 'after');
add_output('shortcuts', 'shortcuts_content', true, 'keyboard_shortcuts', 'start_shortcuts_page', 'after');
add_output('ajax_hm_folders', 'shortcuts_page_link', true, 'keyboard_shortcuts', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'shortcuts'
    ),
    'allowed_output' => array(),
    'allowed_get' => array(),
    'allowed_post' => array()
);
