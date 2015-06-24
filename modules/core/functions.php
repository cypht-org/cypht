<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Format a value for display
 * @subpackage core/functions
 * @param string $name value name to find/format
 * @param array $haystack details to search for the value name
 * @param bool $type optional format type
 * @param mixed $default value to return if the name is not found
 * @return string
 */
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
}

/**
 * set basic message list settings
 * @subpackage core/functions
 * @param string $path message list path
 * @param object $handler hm handler module
 * @return array
 */
function get_message_list_settings($path, $handler) {
    $list_path = '';
    $mailbox_list_title = array();
    $message_list_since = DEFAULT_SINCE;
    $per_source_limit = DEFAULT_PER_SOURCE;

    if ($path == 'unread') {
        $list_path = 'unread';
        $mailbox_list_title = array('Unread');
        $message_list_since = $handler->user_config->get('unread_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
    }
    elseif ($path == 'email') {
        $message_list_since = $handler->user_config->get('all_email_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('all_email_per_source_setting', DEFAULT_PER_SOURCE);
        $list_path = 'email';
        $mailbox_list_title = array('All Email');
    }
    elseif ($path == 'flagged') {
        $list_path = 'flagged';
        $message_list_since = $handler->user_config->get('flagged_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
        $mailbox_list_title = array('Flagged');
    }
    elseif ($path == 'combined_inbox') {
        $list_path = 'combined_inbox';
        $message_list_since = $handler->user_config->get('all_since_setting', DEFAULT_SINCE);
        $per_source_limit = $handler->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
        $mailbox_list_title = array('Everything');
    }
    return array($list_path, $mailbox_list_title, $message_list_since, $per_source_limit);
}

/**
 * Valid interface langs (supported by Google Translate API)
 * @subpackage core/functions
 * @return array
 */
function interface_langs() {
    return array(
        'en' => 'English',
        'es' => 'Spanish',
        'zh' => 'Chinese (Simplified)',
        'zh_TW' => 'Chinese (Traditional)',
        'ar' => 'Arabic',
        'fr' => 'French',
        'nl' => 'Dutch',
        'de' => 'German',
        'hi' => 'Hindi',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'ro' => 'Romanian',
        'sv' => 'Sweedish',
        'th' => 'Thai',
        'vi' => 'Vietnamese',
        'cs' => 'Czech',
        'da' => 'Danish',
        'et' => 'Estonian',
        'fi' => 'Finish',
        'tl' => 'Filipino',
        'ka' => 'Georgian',
        'el' => 'Greek',
        'iw' => 'Hebrew',
        'hu' => 'Hungarian',
        'id' => 'Indonesian',
        'kn' => 'Kannada',
        'lo' => 'Lao',
        'lv' => 'Latvian',
        'lt' => 'Lithuanian',
        'mt' => 'Maltese',
        'mn' => 'Mongolian',
        'ne' => 'Nepalia',
        'no' => 'Norwegian',
        'fa' => 'Persian',
        'pa' => 'Punjabi',
        'sr' => 'Serbian',
        'so' => 'Somali',
        'sw' => 'Swahili',
        'uk' => 'Ukranian',
        'yi' => 'Yiddish',
    );
}

/**
 * Tranlate a human readable time string
 * @subpackage core/functions
 * @param string $str string to translate
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
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
}

/**
 * Format a data source to be a valid JS object
 * @subpackage core/functions
 * @param array $array values to format
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
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
}

/**
 * @subpackage core/functions
 * Group data sources by the "group" attribute if it exists, otherwise use "default"
 * @param array $array list of data sources
 * @return array
 */
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
}

/**
 * Validate search terms
 * @subpackage core/functions
 * @param string $terms search terms to validate
 * @return string
 */
function validate_search_terms($terms) {
    $terms = trim(strip_tags($terms));
    if (!$terms) {
        $terms = false;
    }
    return $terms;
}

/**
 * Validate the name of a search field
 * @subpackage core/functions
 * @param string $fld name to validate
 * @return mixed
 */
function validate_search_fld($fld) {
    if (in_array($fld, array('TEXT', 'BODY', 'FROM', 'SUBJECT'))) {
        return $fld;
    }
    return false;
}

/**
 * Output a select element for the search field
 * @subpackage core/functions
 * @param string $current currently selected field
 * @param object $output_mod Hm_Output_Module
 * @return string
 */
function search_field_selection($current, $output_mod) {
    $flds = array(
        'TEXT' => 'Entire message',
        'BODY' => 'Message body',
        'SUBJECT' => 'Subject',
        'FROM' => 'From',
    );
    $res = '<select id="search_fld" name="search_fld">';
    foreach ($flds as $val => $name) {
        $res .= '<option ';
        if ($current == $val) {
            $res .= 'selected="selected" ';
        }
        $res .= 'value="'.$val.'">'.$output_mod->trans($name).'</option>';
    }
    $res .= '</select>';
    return $res;
}

/**
 * Determine if E-mail modules are active
 * @subpackage core/functions
 * @param string $mod_list list of active module sets
 * @return mixed
 */
function email_is_active($mod_list) {
    if (stristr($mod_list, 'imap') && stristr($mod_list, 'pop3')) {
        return true;
    }
    return false;
}

/**
 * Validate an E-mail using RFC 3696
 * @subpackage core/functions
 * @param string $val value to check
 * @param bool $allow_local flag to allow local addresses with no domain
 * @return bool
 */
function is_email($val, $allow_local=false) {
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
}

/**
 * Do email domain part checks per RFC 3696 section 2
 * @subpackage core/functions
 * @param string $val value to check
 * @return bool
 */
function validate_domain_full($val) {
    /* check for a dot, max allowed length and standard ASCII characters */
    if (strpos($val, '.') === false || strlen($val) > 255 || preg_match("/[^A-Z0-9\-\.]/i", $val) ||
        $val{0} == '-' || $val{(strlen($val) - 1)} == '-') {
        return false;
    }
    return true;
}

/**
 * Do email local part checks per RFC 3696 section 3
 * @subpackage core/functions
 * @param string $val value to check
 * @return bool
 */
function validate_local_full($val) {
    /* check length, "." rules, and for characters > ASCII 127 */
    if (strlen($val) > 64 || $val{0} == '.' || $val{(strlen($val) -1)} == '.' || strstr($val, '..') ||
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
}

/**
 * Get Oauth2 server info
 * @subpackage core/functions
 * @param object $config site config object
 * @return array
 */
function get_oauth2_data($config) {
    $settings = array();
    $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/oauth2.ini';
    if (is_readable($ini_file)) {
        $settings = parse_ini_file($ini_file, true);
    }
    return $settings;
}

function process_site_setting($type, $handler, $callback=false, $default=false) {
    list($success, $form) = $handler->process_form(array('save_settings', $type));
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
        $settings[$type] = $handler->user_config->get($type.'_setting', DEFAULT_PER_SOURCE);
    }
    $handler->out('new_user_settings', $new_settings, false);
    $handler->out('user_settings', $settings, false);
}

/**
 * Return a date for a "received since" value, or just sanitize it
 * @subpackage core/functions
 * @param string $val "received since" value to process
 * @param bool $validate flag to limit to validation only
 */
function process_since_argument($val, $validate=false) {
    $date = false;
    $valid = false;
    if (in_array($val, array('-1 week', '-2 weeks', '-4 weeks', '-6 weeks', '-6 months', '-1 year'), true)) {
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
}

/**
 * Sanitize a "since" setting value for combined pages
 * @param string $val value to check
 * @return sanitized value
 */
function since_setting_callback($val) {
    return process_since_argument($val, true);
}

/**
 * Sanitize a max per source value
 * @param int $val request max
 * @return sanitized max
 */
function max_source_setting_callback($val) {
    if ($val > MAX_PER_SOURCE || $val < 0) {
        return DEFAULT_PER_SOURCE;
    }
    return $val;
}

