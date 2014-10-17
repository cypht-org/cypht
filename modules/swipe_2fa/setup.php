<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('swipe_2fa');
output_source('swipe_2fa');

add_module_to_all_pages('handler', 'swipe_2fa_check', true, 'swipe_2fa', 'save_user_data', 'after');
add_module_to_all_pages('output', 'swipe_2fa_dialog', true, 'swipe_2fa', 'header_start', 'before');

return array(
    'allowed_post' => array(
        'sms_number' => FILTER_SANITIZE_STRING,
        '2fa_sms_response' => FILTER_SANITIZE_STRING
    ),
);

?>
