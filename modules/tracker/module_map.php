<?php

Hm_Handler_Modules::add('home', 'tracker', false, 'http_headers', 'after');
Hm_Handler_Modules::add('ajax_imap_debug', 'tracker', false, 'save_imap_servers', 'after');
Hm_Output_Modules::add('home', 'tracker', false, 'logout', 'after');

?>
