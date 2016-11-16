<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('pgp');
output_source('pgp');

setup_base_page('pgp', 'core');
add_handler('pgp', 'load_pgp_data', true, 'pbp', 'http_headers', 'after');
add_output('pgp', 'pgp_settings_start', true, 'pgp', 'content_section_start', 'after');
add_output('pgp', 'pgp_settings_public_keys', true, 'pgp', 'pgp_settings_start', 'after');
add_output('pgp', 'pgp_settings_private_key', true, 'pgp', 'pgp_settings_public_keys', 'after');
add_output('pgp', 'pgp_settings_end', true, 'pgp', 'pgp_settings_private_key', 'after');

add_handler('ajax_imap_message_content', 'pgp_message_check',  true, 'pgp', 'imap_message_content', 'after');
add_output('ajax_hm_folders', 'pgp_settings_link', true, 'pgp', 'settings_menu_end', 'before');

add_handler('compose', 'pgp_compose_data', true, 'pgp', 'load_user_data', 'after');
add_output('compose', 'pgp_compose_controls', true, 'pgp', 'compose_form_end', 'before');

return array(
    'allowed_pages' => array('pgp'),
    'allowed_cookie' => array(),
    'allowed_server' => array(),
    'allowed_get' => array(),
    'allowed_post' => array()
);


