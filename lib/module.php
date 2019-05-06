<?php

/**
 * Module classes
 * @package framework
 * @subpackage module
 */

/**
 * Module data management. These functions provide an interface for modules (both handler and output)
 * to fetch data set by other modules and to return their own output. Handler modules must use these
 * methods to set a response, output modules must if the format is AJAX, otherwise they should return
 * an HTML5 string
 */ 
trait Hm_Module_Output {

    /* module output */
    protected $output = array();

    /* protected output keys */
    protected $protected = array();

    /* list of appendable keys */
    protected $appendable = array();

    /**
     * @param string $name name to check for
     * @param array $list array to look for name in
     * @param string $type
     * @param mixed $value value
     * @return bool
     */
    protected function check_overwrite($name, $list, $type, $value) {
        if (in_array($name, $list, true)) {
            Hm_Debug::add(sprintf('MODULES: Cannot overwrite %s %s with %s', $type, $name, print_r($value,true)));
            return false;
        }
        return true;
    }

    /**
     * Add a name value pair to the output array
     * @param string $name name of value to store
     * @param mixed $value value
     * @param bool $protected true disallows overwriting
     * @return bool true on success
     */
    public function out($name, $value, $protected=true) {
        if (!$this->check_overwrite($name, $this->protected, 'protected', $value)) {
            return false;
        }
        if (!$this->check_overwrite($name, $this->appendable, 'protected', $value)) {
            return false;
        }
        if ($protected) {
            $this->protected[] = $name;
        }
        $this->output[$name] = $value;
        return true;
    }

    /**
     * append a value to an array, create it if does not exist
     * @param string $name array name
     * @param string $value value to add
     * @return bool true on success
     */
    public function append($name, $value) {
        if (!$this->check_overwrite($name, $this->protected, 'protected', $value)) {
            return false;
        }
        if (array_key_exists($name, $this->output)) {
            if (is_array($this->output[$name])) {
                $this->output[$name][] = $value;
                return true;
            }
            else {
                Hm_Debug::add(sprintf('Tried to append %s to scaler %s', $value, $name));
                return false;
            }
        }
        else {
            $this->output[$name] = array($value);
            $this->appendable[] = $name;
            return true;
        }
    }

    /**
     * Sanitize input string
     * @param string $string text to sanitize
     * @param bool $special_only only use htmlspecialchars not htmlentities
     * @return string sanitized value
     */
    public function html_safe($string, $special_only=false) {
        if ($special_only) {
            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        }
        return htmlentities($string, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }

    /**
     * Concatenate a value
     * @param string $name name to add to
     * @param string $value value to add
     * @return bool true on success
     */
    public function concat($name, $value) {
        if (array_key_exists($name, $this->output)) {
            if (is_string($this->output[$name])) {
                $this->output[$name] .= $value;
                return true;
            }
            else {
                Hm_Debug::add(sprintf('Could not append %s to %s', print_r($value,true), $name));
                return false;
            }
        }
        else {
            $this->output[$name] = $value;
            return true;
        }
    }

    /**
     * Return module output from process()
     * @return array
     */
    public function module_output() {
        return $this->output;
    }

    /**
     * Return protected output field list
     * @return array
     */
    public function output_protected() {
        return $this->protected;
    }

    /**
     * Fetch an output value
     * @param string $name key to fetch the value for
     * @param mixed $default default return value if not found
     * @param string $typed if a default value is given, typecast the result to it's type
     * @return mixed value if found or default
     */
    public function get($name, $default=NULL, $typed=true) {
        if (array_key_exists($name, $this->output)) {
            $val = $this->output[$name];
            if (!is_null($default) && $typed) {
                if (gettype($default) != gettype($val)) {
                    Hm_Debug::add(sprintf('TYPE CONVERSION: %s to %s for %s', gettype($val), gettype($default), $name));
                    settype($val, gettype($default));
                }
            }
            return $val;
        }
        return $default;
    }

    /**
     * Check for a key
     * @param string $name key name
     * @return bool true if found
     */
    public function exists($name) {
        return array_key_exists($name, $this->output);
    }

    /**
     * Check to see if a value matches a list
     * @param string $name name to check
     * @param array $values list to check against
     * @return bool true if found
     */
    public function in($name, $values) {
        if (array_key_exists($name, $this->output) && in_array($this->output[$name], $values, true)) {
            return true;
        }
        return false;
    }
}

/**
 * Methods used to validate handler module operations, like the HTTP request
 * type and target/origin values
 */
trait Hm_Handler_Validate {

    /**
     * Validate HTTP request type, only GET and POST are allowed
     * @param object $session
     * @param object $request
     * @return bool
     */
    public function validate_method($session, $request) {
        if (!in_array(strtolower($request->method), array('get', 'post'), true)) {
            if ($session->loaded) {
                $session->destroy($request);
                Hm_Debug::add(sprintf('LOGGED OUT: invalid method %s', $request->method));
            }
            return false;
        }
        return true;
    }

    /**
     * Validate that the request has matching source and target origins
     * @return bool
     */
    public function validate_origin($session, $request, $config) {
        if (!$session->loaded) {
            return true;
        }
        list($source, $target) = $this->source_and_target($request, $config);
        if (!$this->validate_target($target, $source, $session, $request) ||
            !$this->validate_source($target, $source, $session, $request)) {
            return false;
        }
        return true;
    }

    /**
     * Find source and target values for validate_origin
     * @return string[]
     */
    private function source_and_target($request, $config) {
        $source = false;
        $target = $config->get('cookie_domain', false);
        if ($target == 'none') {
            $target = false;
        }
        $server_vars = array(
            'HTTP_REFERER' => 'source',
            'HTTP_ORIGIN' => 'source',
            'HTTP_HOST' => 'target',
            'HTTP_X_FORWARDED_HOST' => 'target'
        );
        foreach ($server_vars as $header => $type) {
            if (array_key_exists($header, $request->server) && $request->server[$header]) {
                $$type = $request->server[$header];
            }
        }
        return array($source, $target);
    }

    /**
     * @param string $target
     * @param string $source
     * @return boolean
     */
    private function validate_target($target, $source, $session, $request) {
        if (!$target || !$source) {
            $session->destroy($request);
            Hm_Debug::add('LOGGED OUT: missing target origin');
            return false;
        }
        return true;
    }

    /**
     * @param string $target
     * @param string $source
     * @return boolean
     */
    private function validate_source($target, $source, $session, $request) {
        $source = parse_url($source);
        if (!is_array($source) || !array_key_exists('host', $source)) {
            $session->destroy($request);
            Hm_Debug::add('LOGGED OUT: invalid source origin');
            return false;
        }
        if (array_key_exists('port', $source)) {
            $source['host'] .= ':'.$source['port'];
        }
        if ($source['host'] !== $target) {
            $session->destroy($request);
            Hm_Debug::add('LOGGED OUT: invalid source origin');
            return false;
        }
        return true;
    }
}

/**
 * Base class for data input processing modules, called "handler modules"
 *
 * All modules that deal with processing input data extend from this class.
 * It provides access to input and state through the following member variables:
 *
 * $session      The session interface object
 * $request      The HTTP request details object
 * $config       The site config object
 * $user_config  The user settings object for the current user
 *
 * Modules that extend this class need to override the process function
 * Modules can pass information to the output modules using the out() and append() methods,
 * and see data from other modules with the get() method
 * @abstract
 */
abstract class Hm_Handler_Module {

    use Hm_Module_Output;
    use Hm_Handler_Validate;

    /* session object */
    public $session;

    /* request object */
    public $request;

    /* site configuration object */
    public $config;

    /* current request id */
    protected $page = '';

    /* user settings */
    public $user_config;

    public $cache;
    /**
     * Assign input and state sources
     * @param object $parent instance of the Hm_Request_Handler class
     * @param string $page page id
     * @param array $output data from handler modules
     * @param array $protected list of protected output names
     */
    public function __construct($parent, $page, $output=array(), $protected=array()) {
        $this->session = $parent->session;
        $this->request = $parent->request;
        $this->cache = $parent->cache;
        $this->page = $page;
        $this->config = $parent->site_config;
        $this->user_config = $parent->user_config;
        $this->output = $output;
        $this->protected = $protected;
    }

    /**
     * @return string
     */
    private function invalid_ajax_key() {
        if (DEBUG_MODE) {
            Hm_Debug::add('REQUEST KEY check failed');
            Hm_Debug::load_page_stats();
            Hm_Debug::show();
        }
        Hm_Functions::cease(json_encode(array('status' => 'not callable')));;
        return 'exit';
    }

    /**
     * @return string
     */
    private function invalid_http_key() {
        if ($this->session->loaded) {
            $this->session->destroy($this->request);
            Hm_Debug::add('LOGGED OUT: request key check failed');
        }
        Hm_Dispatch::page_redirect('?page=home');
        return 'redirect';
    }

    /**
     * Validate a form key. If this is a non-empty POST form from an
     * HTTP request or AJAX update, it will take the user to the home
     * page if the page_key value is either not present or not valid
     * @return false|string
     */
    public function process_key() {
        if (empty($this->request->post)) {
            return false;
        }
        $key = array_key_exists('hm_page_key', $this->request->post) ? $this->request->post['hm_page_key'] : false;
        $valid = Hm_Request_Key::validate($key);
        if ($valid) {
            return false;
        }
        if ($this->request->type == 'AJAX') {
            return $this->invalid_ajax_key();
        }
        else {
            return $this->invalid_http_key();
        }
    }

    /**
     * Validate a value in a HTTP POST form
     * @param mixed $val
     * @return mixed
     */
    private function check_field($val) {
        switch (true) {
            case is_array($val):
            case trim($val) !== '':
            case $val === '0':
            case $val === 0:
                return $val;
            default:
                return NULL;
        }
    }

    /**
     * Process an HTTP POST form
     * @param array $form list of required field names in the form
     * @return array tuple with a bool indicating success, and an array of valid form values
     */
    public function process_form($form) {
        $new_form = array();
        foreach($form as $name) {
            if (!array_key_exists($name, $this->request->post)) {
                continue;
            }
            $val = $this->check_field($this->request->post[$name]);
            if ($val !== NULL) {
                $new_form[$name] = $val;
            }
        }
        return array((count($form) === count($new_form)), $new_form);
    }

    /**
     * Determine if a module set is enabled
     * @param string $name the module set name to check for
     * @return bool
     */
    public function module_is_supported($name) {
        return in_array(strtolower($name), $this->config->get_modules(true), true);
    }

    /**
     * Handler modules need to override this method to do work
     */
    abstract public function process();
}

/**
 * Base class for output modules
 * All modules that output data to a request must extend this class and define
 * an output() method. It provides form validation, html sanitizing,
 * and string translation services to modules
 * @abstract
 */
abstract class Hm_Output_Module {

    use Hm_Module_Output;

    /* translated language strings */
    protected $lstr = array();

    /* langauge name */
    protected $lang = false;

    /* UI layout direction */
    protected $dir = 'ltr';

    /* Output format (AJAX or HTML5) */
    protected $format = '';

    /**
     * Constructor
     * @param array $input data from handler modules
     * @param array $protected list of protected keys
     */
    public function __construct($input, $protected) {
        $this->output = $input;
        $this->protected = $protected;
    }

    /**
     * Return a translated string if possible
     * @param string $string the string to be translated
     * @return string translated string
     */
    public function trans($string) {
        if (array_key_exists($string, $this->lstr)) {
            if ($this->lstr[$string] === false) {
                return strip_tags($string);
            }
            else {
                return strip_tags($this->lstr[$string]);
            }
        }
        else {
            Hm_Debug::add(sprintf('TRANSLATION NOT FOUND :%s:', $string));
        }
        return str_replace('\n', '<br />', strip_tags($string));
    }

    /**
     * Build output by calling module specific output functions
     * @param string $format output type, either HTML5 or AJAX
     * @param array $lang_str list of language translation strings
     * @return string
     */
    public function output_content($format, $lang_str) {
        $this->lstr = $lang_str;
        $this->format = str_replace('Hm_Format_', '', $format);
        if (array_key_exists('interface_lang', $lang_str)) {
            $this->lang = $lang_str['interface_lang'];
        }
        if (array_key_exists('interface_direction', $lang_str)) {
            $this->dir = $lang_str['interface_direction'];
        }
        return $this->output();
    }

    /**
     * Output modules need to override this method to add to a page or AJAX response
     * @return string
     */
    abstract protected function output();
}

/**
 * Placeholder classes for disabling a module in a set. These allow a module set
 * to replace another module set's assignments with "false" to disable them
 */
class Hm_Output_ extends Hm_Output_Module { protected function output() {} }
class Hm_Handler_ extends Hm_Handler_Module { public function process() {} }
