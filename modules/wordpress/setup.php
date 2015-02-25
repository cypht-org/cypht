<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('wordpress');
output_source('wordpress');

setup_base_page('wordpress_notifications', 'core');
add_output('wordpress_notifications', 'wp_notice_heading', true, 'core', 'content_section_start', 'after');
add_output('wordpress_notifications', 'message_list_start', true, 'core', 'wp_notice_heading', 'after');
add_output('wordpress_notifications', 'wp_notice_end', true, 'core', 'message_list_start', 'after');

add_output('ajax_hm_folders', 'wordpress_folders',  true, 'wordpress', 'folder_list_content_start', 'before');

add_handler('servers', 'setup_wordpress_connect', true, 'wordpress', 'load_user_data', 'after');
add_output('servers', 'wordpress_connect_section', true, 'wordpress', 'server_content_end', 'before');
add_handler('home', 'process_wordpress_authorization', true, 'wordpress', 'load_user_data', 'after');

add_handler('ajax_wordpess_notifications', 'login', false, 'core');
add_handler('ajax_wordpess_notifications', 'load_user_data', true, 'core');
add_handler('ajax_wordpess_notifications', 'language', true, 'core');
add_handler('ajax_wordpess_notifications', 'message_list_type', true, 'core');
add_handler('ajax_wordpess_notifications', 'wp_notification_data',  true);
add_handler('ajax_wordpess_notifications', 'date', true, 'core');
add_handler('ajax_wordpess_notifications', 'http_headers', true, 'core');
add_output('ajax_wordpess_notifications', 'filter_wp_notification_data', true);
return array(
    'allowed_pages' => array(
        'wordpress_notifications',
        'ajax_wordpess_notifications'
    )
);

?>
