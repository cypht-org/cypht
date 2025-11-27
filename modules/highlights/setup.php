<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('highlights');
output_source('highlights');

setup_base_page('highlights', 'core');
add_handler('highlights', 'load_feeds_from_config', true, 'feeds', 'load_user_data', 'after');
add_handler('highlights', 'highlight_process_form', true, 'highlights', 'language', 'after');
add_handler('highlights', 'highlight_page_data', true, 'highlights', 'highlight_process_form', 'after');
add_output('highlights', 'highlight_config_page', true, 'highlights', 'version_upgrade_checker', 'after');

add_handler('message_list', 'highlight_list_data', true, 'highlights', 'load_user_data', 'after');
add_output('message_list', 'highlight_css', true, 'highlights', 'version_upgrade_checker', 'before');

add_output('ajax_hm_folders', 'highlight_link', true, 'highlights', 'settings_save_link', 'after');

return array(
    'allowed_pages' => array(
        'highlights',
    ),
    'allowed_output' => array(
    ),
    'allowed_get' => array(
    ),
    'allowed_post' => array(
        'rule_del_id' => FILTER_VALIDATE_INT,
        'hl_target' => FILTER_UNSAFE_RAW,
        'hl_color' => FILTER_UNSAFE_RAW,
        'hl_source_type' => FILTER_UNSAFE_RAW,
        'hl_important' => FILTER_VALIDATE_BOOLEAN,
        'hl_feeds_unseen' => FILTER_VALIDATE_BOOLEAN,
        'hl_github_unseen' => FILTER_VALIDATE_BOOLEAN,
        'hl_imap_flags' =>  array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_REQUIRE_ARRAY),
        'hl_imap_sources' =>  array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_REQUIRE_ARRAY),
        'hl_github_sources' =>  array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_REQUIRE_ARRAY),
        'hl_feeds_sources' =>  array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_REQUIRE_ARRAY),
    )
);
