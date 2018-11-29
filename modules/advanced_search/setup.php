<?php

if (!defined('DEBUG_MODE')) { die(); }

handler_source('advanced_search');
output_source('advanced_search');

/* advanced search page */
setup_base_page('advanced_search', 'core');
add_handler('advanced_search', 'advanced_search_prepare', true, 'advanced_search', 'date', 'after');
add_output('advanced_search', 'advanced_search_content_start', true, 'advanced_search', 'content_section_start', 'after');
add_output('advanced_search', 'advanced_search_form_start', true, 'advanced_search', 'advanced_search_content_start', 'after');
add_output('advanced_search', 'advanced_search_form_content', true, 'advanced_search', 'advanced_search_form_start', 'after');
add_output('advanced_search', 'advanced_search_form_end', true, 'advanced_search', 'advanced_search_form_content', 'after');
add_output('advanced_search', 'message_list_start', true, 'advanced_search', 'advanced_search_form_end', 'after');
add_output('advanced_search', 'advanced_search_results_table_end', true, 'advanced_search', 'message_list_start', 'after');
add_output('advanced_search', 'advanced_search_content_end', true, 'advanced_search', 'advanced_search_results_table_end', 'after');

/* search page link */
add_output('search', 'advanced_search_link', true, 'advanced_search', 'search_form_end', 'before');

setup_base_ajax_page('ajax_adv_search', 'core');
add_handler('ajax_adv_search', 'advanced_search_prepare', true, 'advanced_search', 'date', 'after');
add_handler('ajax_adv_search', 'process_adv_search_request', true, 'advanced_search', 'imap_oauth2_token_check');
add_output('ajax_adv_search', 'filter_imap_advanced_search', true, 'advanced_search');

/* allowed input */
return array(
    'allowed_pages' => array(
        'advanced_search',
        'ajax_adv_search'
    ),
    'allowed_post' => array(
        'adv_source' => FILTER_SANITIZE_STRING,
        'adv_start' => FILTER_SANITIZE_STRING,
        'adv_source_limit' => FILTER_VALIDATE_INT,
        'adv_end' => FILTER_SANITIZE_STRING,
        'adv_charset' => FILTER_SANITIZE_STRING,
        'adv_flags' => array('filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY),
        'adv_terms' => array('filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY),
        'adv_targets' => array('filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_REQUIRE_ARRAY),
    )
);
