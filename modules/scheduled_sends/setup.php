<?php

handler_source('scheduled_sends');
output_source('scheduled_sends');

add_output('ajax_hm_folders', 'scheduled_folder_link', true, 'scheduled_sends', 'main_menu_content', 'after');

return [];
