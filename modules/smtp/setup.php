<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('smtp');
output_source('smtp');

add_output('compose', 'compose_form', true, 'smtp', 'content_section_start', 'after');

/* servers page */
add_handler('servers', 'load_smtp_servers_from_config', true, 'smtp', 'language', 'after');
add_handler('servers', 'process_add_smtp_server', true, 'smtp', 'load_smtp_servers_from_config', 'after');
add_handler('servers', 'add_smtp_servers_to_page_data', true, 'smtp', 'process_add_smtp_server', 'after');
add_handler('servers', 'save_smtp_servers', true, 'smtp', 'add_smtp_servers_to_page_data', 'after');
add_output('servers', 'add_smtp_server_dialog', true, 'smtp', 'content_section_end', 'before');
add_output('servers', 'display_configured_smtp_servers', true, 'smtp', 'add_smtp_server_dialog', 'after');


return array();

?>
