<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('profiles');
output_source('profiles');


/* profiles page */
setup_base_page('profiles', 'core');
add_output('profiles', 'profile_content', true, 'profiles', 'content_section_start', 'after');
add_output('ajax_hm_folders', 'profile_page_link', true, 'profiles', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'profiles'
    )
);

?>
