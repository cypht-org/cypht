<?php

/**
 * Output content
 * @package framework
 * @subpackage output
 */

/**
 * Base class that controls how data is output
 * @abstract
 */
abstract class Hm_Output {

    /**
     * Extended classes must override this method to output content
     * @param mixed $content data to output
     * @param array $headers headers to send
     * @return void
     */
    abstract protected function output_content($content, $headers);

    /**
     * Wrapper around extended class output_content() calls
     * @param mixed $response data to output
     * @param array $input raw module data
     * @return void
     */
    public function send_response($response, $input = []) {
        $this->output_content($response, $input['http_headers'] ?? []);
    }
}

/**
 * Output request responses using HTTP
 */
class Hm_Output_HTTP extends Hm_Output {

    /**
     * Send HTTP headers
     * @param array $headers headers to send
     * @return void
     */
    protected function output_headers($headers) {
        foreach ($headers as $name => $value) {
            Hm_Functions::header($name.': '.$value);
        }
    }

    /**
     * Send response content to the browser
     * @param mixed $content data to send
     * @param array $headers HTTP headers to set
     * @return void
     */
    protected function output_content($content, $headers = []) {
        $this->output_headers($headers);
        ob_end_clean();
        echo $content;
    }
}

/**
 * Message list struct used for user notices and system debug
 */
trait Hm_List {

    /* message list */
    private static $msgs = [];

    /**
     * Add a message
     * @param string $string message to add
     * @return void
     */
    public static function add($string, $type = 'success') {
        self::$msgs[] = ['type' => $type, 'text' => self::str($string, false)];
    }

    /**
     * Return all messages
     * @return array all messages
     */
    public static function getRaw() {
        return self::$msgs;
    }

    /**
     * Flush all messages
     * @return null
     */
    public static function flush() {
        self::$msgs = [];
    }

    /**
     * Stringify a value
     * @param mixed $mixed value to stringify
     * @return string
     */
    public static function str($mixed, $return_type = true) {
        $type = gettype($mixed);
        if (in_array($type, array('array', 'object'), true)) {
            $str = print_r($mixed, true);
        } elseif ($return_type) {
            $str = sprintf("%s: %s", $type, $mixed);
        } else {
            $str = (string) $mixed;
        }
        return $str;
    }

    public static function get() {
        return array_map(function ($msg) {
            return $msg['text'];
        }, self::$msgs);
    }

    /**
     * Log all messages
     * @return bool
     */
    public static function show() {
        $msgs = array_map(function ($msg) {
            return strtoupper($msg['type']) . ': ' . $msg['text'];
        }, self::$msgs);
        return Hm_Functions::error_log(print_r($msgs, true));
    }
}

/**
 * Notices the user sees
 */
class Hm_Msgs { use Hm_List; }

/**
 * System debug notices
 */
class Hm_Debug {

    use Hm_List {
        add as protected self_add;
    }

    /**
     * @override
     */
    public static function add($string, $type = 'danger') {
        self::self_add($string, $type);
    }

    /**
     * Add page execution stats to the Hm_Debug list
     * @return void
     */
    public static function load_page_stats() {
        self::add(sprintf("PHP version %s", phpversion()), 'info');
        self::add(sprintf("Zend version %s", zend_version()), 'info');
        self::add(sprintf("Peak Memory: %d", (memory_get_peak_usage(true)/1024)), 'info');
        self::add(sprintf("PID: %d", getmypid()), 'info');
        self::add(sprintf("Included files: %d", count(get_included_files())), 'info');
    }
}

/**
 * Easy to use error logging
 * @param mixed $mixed vaule to send to the log
 * @return boolean|null
 */
function elog($mixed) {
    if (DEBUG_MODE) {
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        Hm_Debug::add(sprintf('ELOG called in %s at line %d', $caller['file'], $caller['line']));
        return Hm_Functions::error_log(Hm_Debug::str($mixed));
    }
}
