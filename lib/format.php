<?php

/**
 * Format output
 * @package framework
 * @subpackage format
 */

/**
 * Base class for output formatting. Currently JSON and HTML5 formats are
 * supported. To add support for a new format this class must be extended
 * and the content method needs to be overridden.
 * @abstract
 */
abstract class HM_Format {

    protected $config;

    /**
     * Init
     * @param object $config site config object
     * @return void
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Return combined output from all modules. Must be overridden by specific
     * output classes
     * @param array $output data from the output modules
     * @param array $allowed_output allowed fields for JSON responses
     * @return mixed combined output
     */
    abstract protected function content($input, $allowed_output);
}

/**
 * Handles JSON formatted results for AJAX requests
 */
class Hm_Format_JSON extends HM_Format {

    /**
     * Run modules and merge + filter the result array
     * @param array $input data from the handler modules
     * @param array $lang_str langauge strings
     * @param array $allowed_output allowed fields for JSON responses
     * @return JSON encoded data to be sent to the browser
     */
    public function content($output, $allowed_output) {
        $output['router_user_msgs'] = Hm_Msgs::get();
        $output = $this->filter_output($output, $allowed_output);
        if ($this->config->get('encrypt_ajax_requests', false)) {
            $output = array('payload' => Hm_Crypt::ciphertext(json_encode($output, JSON_FORCE_OBJECT), Hm_Request_Key::generate()));
        }
        return json_encode($output, JSON_FORCE_OBJECT);
    }

    /**
     * Filter data against module set white lists before sending it to the browser
     * @param array $data output module data to filter
     * @param array $allowed set of white list filters
     * @return array filtered data
     */
    public function filter_output($data, $allowed) {
        foreach ($data as $name => $value) {
            if (!array_key_exists($name, $allowed)) {
                unset($data[$name]);
            }
            else {
                if ($allowed[$name][1]) {
                    $new_value = filter_var($value, $allowed[$name][0], $allowed[$name][1]);
                }
                else {
                    $new_value = filter_var($value, $allowed[$name][0]);
                }
                if ($new_value === false && $allowed[$name] != FILTER_VALIDATE_BOOLEAN) {
                    unset($data[$name]);
                }
                else {
                    $data[$name] = $new_value;
                }
            }
        }
        return $data;
    }
}

/**
 * Handles HTML5 formatted results for normal HTTP requests
 */
class Hm_Format_HTML5 extends HM_Format {

    /**
     * Collect and return content from modules for HTTP requests
     * @param array $output data from the output modules
     * @param array $allowed_output allowed fields for JSON responses
     * @return string HTML5 content
     */
    public function content($output, $allowed_output) {
        if (array_key_exists('router_module_list', $output)) {
            unset($output['router_module_list']);
        }
        return implode('', $output);
    }
}

class Hm_Transform {

    static function stringify($data, $version=false, $encoding='base64_encode') {
        if (!is_array($data)) {
            return false;
        }
        return @json_encode(self::hm_encode($data, $encoding));

    }

    static function unstringify($data, $encoding='base64_decode') {
        $result = false;
        if (!is_string($data) || !trim($data)) {
            return false;
        }

        if (substr($data, 0, 2) === 'a:') {
            $result = @unserialize($data);
        }
        elseif (substr($data, 0, 1) === '{') {
            $result = @json_decode($data, true);
        }
        if (is_array($result)) {
            return self::hm_encode($result, $encoding);
        }
        return false;
    }

    static function hm_encode($data, $encoding) {
        $result = array();
        foreach ($data as $name => $val) {
            if (is_array($val)) {
                $result[$name] = self::hm_encode($val, $encoding);
            }
            if (is_string($val)) {
                $result[$name] = $encoding($val);
            }
            else {
                $result[$name] = $val;
            }
        }
        return $result;
    }
}
