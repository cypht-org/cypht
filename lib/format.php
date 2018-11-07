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
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Return combined output from all modules. Must be overridden by specific
     * output classes
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
     * @param array $output data from the handler modules
     * @param array $allowed_output allowed fields for JSON responses
     * @return string encoded data to be sent to the browser
     */
    public function content($output, $allowed_output) {
        $output['router_user_msgs'] = Hm_Msgs::get();
        $output = $this->filter_all_output($output, $allowed_output);
        if ($this->config->get('encrypt_ajax_requests', false)) {
            $output = array('payload' => Hm_Crypt_Base::ciphertext(json_encode($output, JSON_FORCE_OBJECT), Hm_Request_Key::generate()));
        }
        return json_encode($output, JSON_FORCE_OBJECT);
    }

    /**
     * Filter data against module set white lists before sending it to the browser
     * @param array $data output module data to filter
     * @param array $allowed set of white list filters
     * @return array filtered data
     */
    public function filter_all_output($data, $allowed) {
        foreach ($data as $name => $value) {
            if (!array_key_exists($name, $allowed)) {
                unset($data[$name]);
                continue;
            }
            $new_value = $this->filter_output($name, $value, $allowed);
            if ($new_value === NULL) {
                unset($data[$name]);
                continue;
            }
            $data[$name] = $new_value;
        }
        return $data;
    }

    /**
     * Filter a single output value
     * @param string $name
     * @param mixed $value
     * @param array $allowed
     * @return mixed
     */
    private function filter_output($name, $value, $allowed) {
        if ($allowed[$name][1]) {
            $new_value = filter_var($value, $allowed[$name][0], $allowed[$name][1]);
        }
        else {
            $new_value = filter_var($value, $allowed[$name][0]);
        }
        if ($new_value === false && $allowed[$name] != FILTER_VALIDATE_BOOLEAN) {
            return NULL;
        }
        return $new_value;
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

/**
 * binary safe wrapper around json encode/decode using base64
 */
class Hm_Transform {

    /**
     * Convert an array to a string
     * @param mixed $data data to be transformed to a string
     * @param string $encoding encoding to use for values
     * @return string on success, false on failure
     */
    public static function stringify($data, $encoding='base64_encode') {
        if (is_string($data)) {
            return $data;
        }
        if (!is_array($data)) {
            return (string) $data;
        }
        return @json_encode(self::hm_encode($data, $encoding));

    }

    /**
     * @param string $data
     * @return array|false
     */
    public static function convert($data) {
        if (substr($data, 0, 2) === 'a:') {
            return @unserialize($data);
        }
        elseif (substr($data, 0, 1) === '{' || substr($data, 0, 1) === '[') {
            return @json_decode($data, true);
        }
        return false;
    }

    /**
     * Convert a stringified array back to an array
     * @param string|false $data data to be transformed from a string
     * @param string $encoding encoding to use for values
     * @param boolean $return return original string if true
     * @return mixed array on success, false or original string on failure
     */
    public static function unstringify($data, $encoding='base64_decode', $return=false) {
        if (!is_string($data) || !trim($data)) {
            return false;
        }
        $result = self::convert($data);
        if (is_array($result)) {
            return self::hm_encode($result, $encoding);
        }
        if ($return) {
            return $data;
        }
        return false;
    }

    /**
     * Recursively encode values in an array
     * @param array $data data to encode values for
     * @param string $encoding the type of encoding to use
     * @return array
     */
    public static function hm_encode($data, $encoding) {
        $result = array();
        foreach ($data as $name => $val) {
            if (is_array($val)) {
                $result[$name] = self::hm_encode($val, $encoding);
            }
            else {
                if (is_string($val)) {
                    $result[$name] = $encoding($val);
                }
                else {
                    $result[$name] = $val;
                }
            }
        }
        return $result;
    }
}
