<?php

Hm_Handler_Modules::add('servers', 'load_pop3_servers_from_config',  true, 'date', 'after');
Hm_Handler_Modules::add('servers', 'process_add_pop3_server', true, 'load_pop3_servers_from_config', 'after');
Hm_Handler_Modules::add('servers', 'add_pop3_servers_to_page_data', true, 'process_add_pop3_server', 'after');
Hm_Handler_Modules::add('servers', 'save_pop3_servers',  true, 'add_pop3_servers_to_page_data', 'after');

Hm_Output_Modules::add('servers', 'add_pop3_server_dialog', true, 'display_configured_imap_servers', 'after');
Hm_Output_Modules::add('servers', 'display_configured_pop3_servers', true, 'add_pop3_server_dialog', 'after');

return array(
    'allowed_post' => array(
        'new_pop3_name' => FILTER_SANITIZE_STRING,
        'new_pop3_address' => FILTER_SANITIZE_STRING,
        'new_pop3_port' => FILTER_SANITIZE_STRING,
        'submit_pop3_server' => FILTER_SANITIZE_STRING
    )
);

?>
