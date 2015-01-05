<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Base class for output formatting. Currently JSON and HTML5 formats are
 * supported. To add support for a new format this class must be extended
 * and the content method needs to be overridden.
 */
abstract class HM_Format {

    /* output modules */
    protected $modules = array();

    /**
     * Return combined output from all modules. Must be overridden by specific
     * output classes
     *
     * @param $input array data from the handler modules
     * @param $lang_str array language definitions
     * @param $allowed_output array allowed fields for JSON responses
     *
     * @return mixed combined output
     */
    abstract protected function content($input, $lang_str, $allowed_output);

    /**
     * Setup and run the abstract content() function
     *
     * @param $input array data from the handler modules
     * @param $allowed_output array allowed fields for JSON responses
     *
     * @return mixed formatted content
     */
    public function format_content($input, $allowed_output) {
        $lang_strings = array();
        if (array_key_exists('language', $input)) {
            $lang_strings = $this->get_language($input['language']);
        }
        $this->modules = Hm_Output_Modules::get_for_page($input['router_page_name']);
        $formatted = $this->content($input, $lang_strings, $allowed_output);
        return $formatted;
    }

    /**
     * Load language translation strings
     *
     * @param $lang string langauge name
     *
     * @return array list of translated strings
     */
    public function get_language($lang) {
        $strings = array();
        if (file_exists(APP_PATH.'language/'.$lang.'.php')) {
            $strings = require APP_PATH.'language/'.$lang.'.php';
        }
        return $strings;
    }

    /**
     * Run output modules and collect the results
     *
     * @param $input array data from the handler modules
     * @param $format string output format type, either JSON or HTML5
     * @param $lang_str array langauge strings
     *
     * @return mixed module results
     */
    protected function run_modules($input, $format, $lang_str) {
        $mod_output = array();
        $protected = array();
        foreach ($this->modules as $name => $args) {
            $name = "Hm_Output_$name";
            if (class_exists($name)) {
                if (!$args[1] || ($args[1] && $input['router_login_state'])) {
                    $mod = new $name($input, $protected);
                    if ($format == 'JSON') {
                        $mod->output_content($format, $lang_str, $protected);
                        $input = $mod->module_output();
                        $protected = $mod->output_protected();
                    }
                    else {
                        $mod_output[] = $mod->output_content($format, $lang_str, array());
                    }
                }
            }
            else {
                Hm_Debug::add(sprintf('Output module %s activated but not found', $name));
            }
        }
        if (empty($mod_output)) {
            return $input;
        }
        return $mod_output;
    }
}

/**
 * Handles JSON formatted results for AJAX requests
 */
class Hm_Format_JSON extends HM_Format {

    /**
     * Run modules and merge + filter the result array
     *
     * @param $input array data from the handler modules
     * @param $lang_str array langauge strings
     * @param $allowed_output array allowed fields for JSON responses
     *
     * @return JSON encoded data to be sent to the browser
     */
    public function content($input, $lang_str, $allowed_output) {
        $input['router_user_msgs'] = Hm_Msgs::get();
        $output = $this->run_modules($input, 'JSON', $lang_str);
        $output = $this->filter_output($output, $allowed_output);
        return json_encode($output, JSON_FORCE_OBJECT);
    }

    /**
     * Filter data against module set white lists before sending it to the browser
     *
     * @param $data array output module data to filter
     * @param $allowed array set of white list filters
     *
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
     *
     * @param $input array data from the handler modules
     * @param $lang_str array langauge strings
     * @param $allowed_output array allowed fields for JSON responses
     *
     * @return string HTML5 content
     */
    public function content($input, $lang_str, $allowed_output) {
        $output = $this->run_modules($input, 'HTML5', $lang_str);
        return implode('', $output);
    }
}

?>
