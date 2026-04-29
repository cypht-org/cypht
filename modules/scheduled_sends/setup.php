<?php

handler_source('scheduled_sends');
output_source('scheduled_sends');

add_output('ajax_hm_folders', 'scheduled_folder_link', true, 'scheduled_sends', 'main_menu_content', 'after');

add_handler('message_list', 'load_scheduled_sends_sources', true, 'scheduled_sends', 'load_imap_servers_for_message_list', 'after');

return [];
