<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('create_account');
output_source('create_account');


/* help page */
setup_base_page('create_account', 'core');
add_output('create_account', 'create_form', false, 'create_account', 'content_section_start', 'after');
replace_module('output', 'login', 'no_login', 'create_account');

/* input/output */
return array(
    'allowed_pages' => array(
        'create_account'
    )
);

?>
