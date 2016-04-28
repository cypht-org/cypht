<?php

/**
 * IMAP folder management modules
 * @package modules
 * @subpackage imap_folders/functions
 */

if (!defined('DEBUG_MODE')) { die(); }

handler_source('imap_folders');
output_source('imap_folders');

setup_base_page('folders', 'core');
add_output('folders', 'folders_content', true, 'imap_folders', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'folders_page_link', true, 'imap_folders', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'folders',
    ),
    'allowed_output' => array(),
    'allowed_get' => array(),
    'allowed_post' => array()
);
