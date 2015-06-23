<?php

if (!defined('DEBUG_MODE')) { die(); }

/* dev resources and info page are only available in debug mode */
if (DEBUG_MODE) {

    /* setup sources */
    handler_source('developer');
    output_source('developer');

    /* info page */
    setup_base_page('info', 'core');
    add_handler('info', 'process_server_info', true, 'developer', 'load_user_data', 'after');
    add_output('info', 'info_heading', true, 'developer', 'content_section_start', 'after');
    add_output('info', 'server_information', true, 'developer', 'info_heading', 'after');
    add_output('info', 'server_status_start', true, 'developer', 'server_information', 'after');
    add_output('info', 'server_status_end', true, 'developer', 'server_status_start', 'after');
    add_output('info', 'config_map', true, 'developer', 'server_status_end', 'after');

    /* folder list */
    add_output('ajax_hm_folders', 'info_page_link', true, 'developer', 'settings_menu_end', 'before');
    add_output('ajax_hm_folders', 'developer_doc_link', true, 'developer', 'settings_menu_end', 'before');

    /* developer docs */
    setup_base_page('dev', 'core');
    add_output('dev', 'dev_content', true, 'developer', 'content_section_start', 'after');

    /* add pages */
    return array(
        'allowed_pages' => array(
            'info',
            'dev',
        )
    );

}
return array();


