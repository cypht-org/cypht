<?php

/**
 * Format output
 * @package framework
 * @subpackage format
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Base class for output formatting. Currently JSON and HTML5 formats are
 * supported. To add support for a new format this class must be extended
 * and the content method needs to be overridden.
 * @abstract
 */
abstract class HM_Format {

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
        $input['router_user_msgs'] = Hm_Msgs::get();
        $output = $this->filter_output($output, $allowed_output);
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
        return implode('', $output);
    }
}

?>
