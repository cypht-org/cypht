<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('keyboard_shortcuts');
output_source('keyboard_shortcuts');

return array(
    'allowed_pages' => array(),
    'allowed_output' => array(),
    'allowed_get' => array(),
    'allowed_post' => array()
);
