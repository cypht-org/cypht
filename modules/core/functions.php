<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

/**
 * Format a value for display
 * @subpackage core/functions
 * @param string $name value name to find/format
 * @param array $haystack details to search for the value name
 * @param bool $type optional format type
 * @param mixed $default value to return if the name is not found
 * @return string
 */
if (!hm_exists('display_value')) {
function display_value($name, $haystack, $type=false, $default='') {
    if (!array_key_exists($name, $haystack)) {
        return $default;
    }
    $value = $haystack[$name];
    $res = false;
    if ($type) {
        $name = $type;
    }
    switch($name) {
        case 'from':
            $value = preg_replace("/(\<.+\>)/U", '', $value);
            $res = str_replace('"', '', $value);
            break;
        case 'date':
            $res = human_readable_interval($value);
            break;
        case 'time':
            $res = strtotime($value);
            break;
        default:
            $res = $value;
            break;
    }
    return $res;
}}

/**
 * Valid interface langs
 * @subpackage core/functions
 * @return array
 */
if (!hm_exists('interface_langs')) {
function interface_langs() {
    return array(
        'en' => 'English',
        'de' => 'German',
        'es' => 'Spanish',
        'fr' => 'French',
        'et' => 'Estonian',
        'it' => 'Italian',
        'ru' => 'Russian',
        'ro' => 'Romanian',
        'nl' => 'Dutch',
        'ja' => 'Japanese',
        'hu' => 'Hungarian',
        'pt-BR' => 'Brazilian Portuguese',
        'az' => 'Azerbaijani',
    );
}}

/**
 * Tranlate a human readable time string
 * @subpackage core/functions
 * @param string $str string to translate
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('translate_time_str')) {
function translate_time_str($str, $output_mod) {
    $parts = explode(',', $str);
    $res = array();
    foreach ($parts as $part) {
        $part = trim($part);
        if (preg_match("/(\d+)/", $part, $matches)) {
            $res[] = sprintf($output_mod->trans(preg_replace("/(\d+)/", '%d', $part)), $matches[1]);
        }
    }
    if (!empty($res)) {
        return implode(', ', $res);
    }
    return $str;
}}

/**
 * Format a data source to be a valid JS object
 * @subpackage core/functions
 * @param array $array values to format
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('format_data_sources')) {
function format_data_sources($array, $output_mod) {
    $result = '';
    $default = false;
    $groups = group_data_sources($array);
    foreach ($groups as $group_name => $sources) {
        $objects = array();
        foreach ($sources as $values) {
            $items = array();
            foreach ($values as $name => $value) {
                if ($name == 'callback') {
                    $items[] = $output_mod->html_safe($name).':'.$output_mod->html_safe($value);
                }
                else {
                    $items[] = $output_mod->html_safe($name).':"'.$output_mod->html_safe($value).'"';
                }
            }
            $objects[] = '{'.implode(',', $items).'}';
        }
        $function = 'hm_data_sources';
        if ($group_name != 'default') {
            $function .= '_'.$group_name;
        }
        else {
            $default = true;
        }
        $result .= 'var '.$function.' = function() { return ['.implode(',', $objects).']; };';
    }
    if (!$default) {
        $result .= 'var hm_data_sources = function() { return []; };';
    }
    return $result;
}}

/**
 * Group data sources by the "group" attribute if it exists, otherwise use "default"
 * @subpackage core/functions
 * @param array $array list of data sources
 * @return array
 */
if (!hm_exists('group_data_sources')) {
function group_data_sources($array) {
    $groups = array();
    foreach($array as $vals) {
        $key = 'default';
        if (array_key_exists('group', $vals)) {
            $key = $vals['group'];
        }
        $groups[$key][] = $vals;
    }
    return $groups;
}}

/**
 * Determine if E-mail modules are active
 * @subpackage core/functions
 * @param array $mod_list list of active module sets
 * @return mixed
 */
if (!hm_exists('email_is_active')) {
function email_is_active($mod_list) {
    if (in_array('imap', $mod_list, true) || in_array('pop3', $mod_list, true)) {
        return true;
    }
    return false;
}}

/**
 * Validate an E-mail using RFC 3696
 * @subpackage core/functions
 * @param string $val value to check
 * @param bool $allow_local flag to allow local addresses with no domain
 * @return bool
 */
if (!hm_exists('is_email_address')) {
function is_email_address($val, $allow_local=false) {
    $val = trim($val, "<>");
    $domain = false;
    $local = false;
    if (!trim($val) || strlen($val) > 320) {
        return false;
    }
    if (strpos($val, '@') !== false) {
        $local = substr($val, 0, strrpos($val, '@'));
        $domain = substr($val, (strrpos($val, '@') + 1));
    }
    else {
        $local = $val;
    }
    if (!$local || (!$allow_local && !$domain)) {
        return false;
    }
    else {
        if ($domain && !validate_domain_full($domain)) {
            return false;
        }
        if (!validate_local_full($local)) {
            return false;
        }
    }
    return true;
}}

/**
 * Do email domain part checks per RFC 3696 section 2
 * @subpackage core/functions
 * @param string $val value to check
 * @return bool
 */
if (!hm_exists('validate_domain_full')) {
function validate_domain_full($val) {
    /* check for a dot, max allowed length and standard ASCII characters */
    if (strpos($val, '.') === false || strlen($val) > 255 || preg_match("/[^A-Z0-9\-\.]/i", $val) ||
        $val[0] == '-' || $val[(strlen($val) - 1)] == '-') {
        return false;
    }
    return true;
}}

/**
 * Do email local part checks per RFC 3696 section 3
 * @subpackage core/functions
 * @param string $val value to check
 * @return bool
 */
if (!hm_exists('validate_local_full')) {
function validate_local_full($val) {
    /* check length, "." rules, and for characters > ASCII 127 */
    if (strlen($val) > 64 || $val[0] == '.' || $val[(strlen($val) -1)] == '.' || strstr($val, '..') ||
        preg_match('/[^\x00-\x7F]/',$val)) {
        return false;
    }
    /* remove escaped characters and quoted strings */
    $local = preg_replace("/\\\\.{1}/", '', $val);
    $local = preg_replace("/\"[^\"]+\"/", '', $local);

    /* validate remaining unescaped characters */
    if (preg_match("/[[:print:]]/", $local) && !preg_match("/[@\\\",\[\]]/", $local)) {
        return true;
    }
    return false;
}}

/**
 * Get Oauth2 server info
 * @subpackage core/functions
 * @param object $config site config object
 * @return array
 */
if (!hm_exists('get_oauth2_data')) {
function get_oauth2_data($config) {
    return get_ini($config, 'oauth2.ini', true);
}}

/**
 * Process user input for a site setting and prep it to be saved
 * @subpackage core/functions
 * @param string $type the name of the setting
 * @param object $handler hm hanndler module object
 * @param function $callback a function to sanitize the submitted value
 * @param mixed $default a default to use if callback is not submitted
 * @param bool $checkbox true if this is a checkbox setting
 * @return void
 */
if (!hm_exists('process_site_setting')) {
function process_site_setting($type, $handler, $callback=false, $default=false, $checkbox=false) {
    if ($checkbox) {
        list($success, $form) = $handler->process_form(array('save_settings'));
        if (array_key_exists($type, $handler->request->post)) {
            $form = array($type => $handler->request->post[$type]);
        }
        else {
            $form = array($type => false);
        }
    }
    else {
        list($success, $form) = $handler->process_form(array('save_settings', $type));
    }
    $new_settings = $handler->get('new_user_settings', array());
    $settings = $handler->get('user_settings', array());

    if ($success) {
        if (function_exists($callback)) {
            $result = $callback($form[$type]);
        }
        else {
            $result = $default;
        }
        $new_settings[$type.'_setting'] = $result;
    }
    else {
        $settings[$type] = $handler->user_config->get($type.'_setting', $default);
    }
    $handler->out('new_user_settings', $new_settings, false);
    $handler->out('user_settings', $settings, false);
}}

/**
 * Return a date for a "received since" value, or just sanitize it
 * @subpackage core/functions
 * @param string $val "received since" value to process
 * @param bool $validate flag to limit to validation only
 */
if (!hm_exists('process_since_argument')) {
function process_since_argument($val, $validate=false) {
    $date = false;
    $valid = false;
    if (in_array($val, array('-1 week', '-2 weeks', '-4 weeks', '-6 weeks', '-6 months', '-1 year', '-5 years'), true)) {
        $valid = $val;
        $date = date('j-M-Y', strtotime($val));
    }
    else {
        $val = 'today';
        $valid = $val;
        $date = date('j-M-Y');
    }
    if ($validate) {
        return $valid;
    }
    return $date;
}}

/**
 * Sanitize a "since" setting value for combined pages
 * @subpackage core/functions
 * @param string $val value to check
 * @return sanitized value
 */
if (!hm_exists('since_setting_callback')) {
function since_setting_callback($val) {
    return process_since_argument($val, true);
}}

/**
 * Sanitize a max per source value
 * @subpackage core/functions
 * @param int $val request max
 * @return sanitized max
 */
if (!hm_exists('max_source_setting_callback')) {
function max_source_setting_callback($val) {
    if ($val > MAX_PER_SOURCE || $val < 0) {
        return DEFAULT_PER_SOURCE;
    }
    return $val;
}}

/**
 * Save user settings from the session to permanent storage
 * @subpackage core/functions
 * @param object $handler hm handler module object
 * @param array $form sanitized user input
 * @param bool $logout true if this is a save + logout request
 * @return void
 */
if (!hm_exists('save_user_settings')) {
function save_user_settings($handler, $form, $logout) {
    $user = $handler->session->get('username', false);
    $path = $handler->config->get('user_settings_dir', false);

    if ($handler->session->auth($user, $form['password'])) {
        $pass = $form['password'];
    }
    else {
        Hm_Msgs::add('ERRIncorrect password, could not save settings to the server');
        $pass = false;
    }
    if ($user && $path && $pass) {
        $handler->user_config->save($user, $pass);
        $handler->session->set('changed_settings', array());
        if ($logout) {
            $handler->session->destroy($handler->request);
            Hm_Msgs::add('Saved user data on logout');
            Hm_Msgs::add('Session destroyed on logout');
        }
        else {
            Hm_Msgs::add('Settings saved');
        }
    }
}}

/**
 * Setup commonly used modules for an ajax request
 * @subpackage core/functions
 * @param string $name the page id
 * @param string $source the module set name
 * @return void
 */
if (!hm_exists('setup_base_ajax_page')) {
function setup_base_ajax_page($name, $source=false) {
    add_handler($name, 'login', false, $source);
    add_handler($name, 'default_page_data', true, $source);
    add_handler($name, 'load_user_data', true, $source);
    add_handler($name, 'language',  true, $source);
    add_handler($name, 'date', true, $source);
    add_handler($name, 'http_headers', true, $source);
}}

/**
 * Setup commonly used modules for a page
 * @subpackage core/functions
 * @param string $name the page id
 * @param string $source the module set name
 * @return void
 */
if (!hm_exists('setup_base_page')) {
function setup_base_page($name, $source=false) {
    add_handler($name, 'stay_logged_in', false, $source);
    add_handler($name, 'login', false, $source);
    add_handler($name, 'default_page_data', true, $source);
    add_handler($name, 'load_user_data', true, $source);
    add_handler($name, 'message_list_type', true);
    add_handler($name, 'language',  true, $source);
    add_handler($name, 'process_search_terms', true, $source);
    add_handler($name, 'title', true, $source);
    add_handler($name, 'date', true, $source);
    add_handler($name, 'save_user_data', true, $source);
    add_handler($name, 'logout', true, $source);
    add_handler($name, 'http_headers', true, $source);

    add_output($name, 'header_start', false, $source);
    add_output($name, 'header_css', false, $source);
    add_output($name, 'header_content', false, $source);
    add_output($name, 'js_data', true, $source);
    add_output($name, 'js_search_data', true, $source);
    add_output($name, 'header_end', false, $source);
    add_output($name, 'content_start', false, $source);
    add_output($name, 'login_start', false, $source);
    add_output($name, 'login', false, $source);
    add_output($name, 'login_end', false, $source);
    add_output($name, 'loading_icon', true, $source);
    add_output($name, 'date', true, $source);
    add_output($name, 'msgs', false, $source);
    add_output($name, 'folder_list_start', true, $source);
    add_output($name, 'folder_list_end', true, $source);
    add_output($name, 'content_section_start', true, $source);
    add_output($name, 'content_section_end', true, $source);
    add_output($name, 'save_reminder', true, $source);
    add_output($name, 'page_js', true, $source);
    add_output($name, 'content_end', false, $source);
}}

/**
 * Merge array details for folder sources
 * @subpackage core/functions
 * @param array $folder_sources list of folder list entries
 * @return array
 */
if (!hm_exists('merge_folder_list_details')) {
function merge_folder_list_details($folder_sources) {
    $res = array();
    if (!is_array($folder_sources)) {
        return $res;
    }
    foreach ($folder_sources as $vals) {
        if (array_key_exists($vals[0], $res)) {
            $res[$vals[0]] .= $vals[1];
        }
        else {
            $res[$vals[0]] = $vals[1];
        }
    }
    ksort($res);
    return $res;
}}

/**
 * Get the contents of an ini file, first check the config
 * @subpackage core/functions
 */
if (!hm_exists('get_ini')) {
function get_ini($config, $name, $sections=false) {
    if (!DEBUG_MODE) {
        $data = $config->get($name, array());
        if (is_array($data) && count($data) > 0) {
            return $data;
        }
    }
    $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/'.$name;
    if (is_readable($ini_file)) {
        $data = parse_ini_file($ini_file, $sections);
        if (is_array($data) && count($data) > 0) {
            return $data;
        }
    }
    return array();
}}

/**
 * Determine the correct TLS connection type to use based
 * on what this version of PHP supports
 * @return const
 */
if (!hm_exists('get_tls_stream_type')) {
function get_tls_stream_type() {
    $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        $method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
    }
    return $method;
}}

/**
 * List of valid start page options
 * @return array
 */
if (!hm_exists('start_page_opts')) {
function start_page_opts() {
    return array(
        'None' => 'none',
        'Home' => 'page=home',
        'Everything' => 'page=message_list&list_path=combined_inbox',
        'Unread' => 'page=message_list&list_path=unread',
        'Flagged' => 'page=message_list&list_path=flagged',
        'Compose' => 'page=compose'
    );
}}

/**
 * List of valid default sort order options
 * @return array
 */
if (!hm_exists('default_sort_order_opts')) {
function default_sort_order_opts() {
    return array(
        'arrival' => 'Arrival Date',
        'date' => 'Sent Date',
    );
}}

/**
 * See if a host + username is already in a server list
 * @param class $list class to check
 * @param int $id server id to get hostname from
 * @param string $user username to check for
 * @return bool
 */
if (!hm_exists('in_server_list')) {
function in_server_list($list, $id, $user) {
    $exists = false;
    $server = $list::dump($id);
    $name = false;
    if (is_array($server) && array_key_exists('server', $server)) {
        $name = $server['server'];
    }
    if (!$name) {
        return false;
    }
    foreach ($list::dump() as $server_id => $vals) {
        if ($id == $server_id) {
            continue;
        }
        if (array_key_exists('user', $vals) && $vals['user'] == $user && $vals['server'] == $name) {
            $exists = true;
            break;
        }
    }
    return $exists;
}}

/**
 * @subpackage core/functions
 */
if (!hm_exists('profiles_by_smtp_id')) {
function profiles_by_smtp_id($profiles, $id) {
    $res = array();
    foreach ($profiles as $vals) {
        if (!is_array($vals)) {
            continue;
        }
        if ($vals['smtp_id'] == $id) {
            $res[] = $vals;
        }
    }
    return $res;
}}
