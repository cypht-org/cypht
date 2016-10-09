<?php
if (!defined('DEBUG_MODE')) { die(); }

handler_source('recaptcha');
output_source('recaptcha');

add_module_to_all_pages('handler', 'process_recaptcha', false, 'recaptcha', 'login', 'before');
add_module_to_all_pages('output', 'recaptcha_form', false, 'recaptcha', 'login_end', 'before');
add_module_to_all_pages('output', 'recaptcha_script', false, 'recaptcha', 'header_end', 'before');

return array(
    'allowed_post' => array(
        'g-recaptcha-response' => FILTER_SANITIZE_STRING
    )
);
