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

/**
 * Process user input for a site setting and prep it to be saved
 * @param string $type the name of the setting
 * @param object $handler hm hanndler module object
 * @param function $callback a function to sanitize the submitted value
 * @param mixed $default a default to use if callback is not submitted
 * @param bool $checkbox true if this is a checkbox setting
 * @return void
 */
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

/**
 * Save user settings from the session to permanent storage
 * @param object $handler hm handler module object
 * @param array $form sanitized user input
 * @param bool $logout true if this is a save + logout request
 * @return void
 */
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
}

/**
 * Setup commonly used modules for a page
 * @param string $name the page id
 * @param string $source the module set name
 * @return void
 */
function setup_base_page($name, $source=false) {
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
    add_output($name, 'js_data', true, $source);
    add_output($name, 'js_search_data', true, $source);
    add_output($name, 'header_css', false, $source);
    add_output($name, 'header_content', false, $source);
    add_output($name, 'header_end', false, $source);
    add_output($name, 'content_start', false, $source);
    add_output($name, 'login', false, $source);
    add_output($name, 'loading_icon', true, $source);
    add_output($name, 'date', true, $source);
    add_output($name, 'msgs', false, $source);
    add_output($name, 'folder_list_start', true, $source);
    add_output($name, 'folder_list_end', true, $source);
    add_output($name, 'content_section_start', true, $source);
    add_output($name, 'content_section_end', true, $source);
    add_output($name, 'save_reminder', true, $source);
    add_output($name, 'page_js', true, $source);
    add_output($name, 'content_end', true, $source);
}

/**
 * Merge array details for folder sources
 * @param array $folder_sources list of folder list entries
 * @return array
 */
function merge_folder_list_details($folder_sources) {
    $res = array();
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
}

class Hm_Address_Field {

    public static function parse($string) {
        $marker = true;
        $results = array();

        while ($marker !== false) {
            list($marker, $token, $string) = self::get_token($string);
            if (is_email($token)) {
                list($name, $marker) = self::find_name_field($string);
                if ($marker > -1) {
                    $string = substr($string, 0, $marker);
                }
                else {
                    $marker = false;
                }
                $results[] = array('email' => $token, 'name' => $name);
            }
        }
        return $results;
    }

    private static function get_token($string) {
        $marker = strrpos($string, ' ');
        $token = trim(ltrim(substr($string, $marker)), '<>');
        $string = substr($string, 0, $marker);
        return array($marker, $token, $string);
    }

    private static function is_quote($string, $i, $quote) {
        if (in_array($string{$i}, array('"', "'"), true)) {
            if (!self::embeded_quote($string, $i)) {
                $quote = $quote ? false : true;
            }
        }
        return $quote;
    }

    private static function find_name_field($string) {
        $quote = false;
        $result = '';
        for ($i = strlen($string) - 1;$i>-1; $i--) {
            $quote = self::is_quote($string, $i, $quote);
            if (self::delimiter_found($string, $i, $quote)) {
                break;
            }
            $result .= $string{$i};
        }
        return array(strrev(trim(trim($result),'"\'')), $i);
    }

    private static function embeded_quote($string, $i) {
        return $i > 0 && $string{$i -1} == '\\';
    }

    private static function delimiter_found($string, $i, $quote) {
        return !$quote && in_array($string{$i}, array(',', ';'), true);
    }
}
