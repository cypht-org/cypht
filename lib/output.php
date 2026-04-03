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

    protected $content;
    protected $data;

    public function __construct($content = null, $data = []) {
        $this->content = $content;
        $this->data = $data;
    }

    /**
     * Extended classes must override this method to output content
     * @param mixed $content data to output
     * @param array $headers headers to send
     * @return void
     */
    abstract protected function output_content();

    /**
     * Wrapper around extended class output_content() calls
     * @param mixed $response data to output
     * @param array $input raw module data
     * @return void
     */
    public function send_response() {
        $this->output_content();
    }
}

/**
 * Output request responses using HTTP
 */
class Hm_Output_HTTP extends Hm_Output {

    /**
     * Send HTTP headers
     * @return void
     */
    protected function output_headers() {
        $headers = $this->data['http_headers'] ?? [];
        foreach ($headers as $name => $value) {
            Hm_Functions::header($name.': '.$value);
        }
    }

    /**
     * Send response content to the browser
     * @return void
     */
    protected function output_content() {
        $this->output_headers();
        ob_end_clean();

        echo $this->get_content($this->content);
    }

    public function get_content() {
        $this->output_headers();
        
        // Append debug panel if DEBUG_MODE is enabled and it's an HTML response
        if (DEBUG_MODE && is_string($this->content) && stripos($this->content, '</body>') !== false) {
            $debug_panel = $this->get_debug_panel_html();
            return str_replace('</body>', $debug_panel.'</body>', $this->content);
        }
        return $this->content;
    }

    /**
     * Generate debug panel HTML
     * @return string debug panel HTML
     */
    private function get_debug_panel_html() {
        $all_msgs = Hm_Debug::getRaw();
        if (empty($all_msgs)) {
            return '';
        }

        // Level priority map — higher number = more severe
        $level_priority = [
            'debug'   => 0,
            'info'    => 1,
            'success' => 1,
            'notice'  => 2,
            'warning' => 3,
            'error'   => 4,
            'danger'  => 4,
            'critical'=> 5,
        ];

        // Map LOG_LEVEL env value to minimum priority threshold
        $log_level_map = [
            'DEBUG'     => 0,
            'INFO'      => 1,
            'NOTICE'    => 2,
            'WARNING'   => 3,
            'ERROR'     => 4,
            'CRITICAL'  => 5,
            'ALERT'     => 5,
            'EMERGENCY' => 5,
        ];
        $min_level_name = strtoupper(env('LOG_LEVEL', 'DEBUG'));
        $min_priority = $log_level_map[$min_level_name] ?? 0;

        $msgs = array_filter($all_msgs, function($msg) use ($level_priority, $min_priority) {
            $type = strtolower($msg['type'] ?? 'info');
            $priority = $level_priority[$type] ?? 1;
            return $priority >= $min_priority;
        });

        if (empty($msgs)) {
            return '';
        }

        $type_icons = [
            'danger' => 'bi-exclamation-triangle-fill text-danger',
            'error' => 'bi-exclamation-triangle-fill text-danger',
            'warning' => 'bi-exclamation-circle-fill text-warning',
            'info' => 'bi-info-circle-fill text-info',
            'success' => 'bi-check-circle-fill text-success',
            'debug' => 'bi-bug-fill text-secondary',
        ];

        $output = '<div id="cypht-debug-panel" style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 9998; background: #fff; border-top: 3px solid #333; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); max-height: 400px; overflow: hidden; display: none;">';
        $output .= '<div style="padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; cursor: pointer;" onclick="var messages = document.getElementById(\'cypht-debug-messages\'); messages.style.display = (messages.style.display === \'none\' ? \'block\' : \'none\');">';
        $output .= '<strong><i class="bi bi-terminal-fill"></i> Debug Panel</strong> <span class="badge bg-secondary">'.count($msgs).' / '.count($all_msgs).' messages</span> <small class="text-muted">(≥ '.$min_level_name.')</small>';
        $output .= '<button type="button" class="btn-close float-end" onclick="event.stopPropagation(); document.getElementById(\'cypht-debug-panel\').style.display = \'none\';" aria-label="Close"></button>';
        $output .= '</div>';
        $output .= '<div id="cypht-debug-messages" style="max-height: 350px; overflow-y: auto; padding: 10px 15px; font-size: 13px; font-family: monospace;">';

        foreach ($msgs as $msg) {
            $type = $msg['type'] ?? 'info';
            $icon = $type_icons[$type] ?? 'bi-info-circle-fill text-muted';
            $text = htmlspecialchars($msg['text'] ?? '', ENT_QUOTES, 'UTF-8');

            $output .= '<div style="margin: 5px 0; padding: 8px; border-left: 3px solid; border-radius: 3px;" class="border-'.$type.'">';
            $output .= '<i class="bi '.$icon.'"></i> ';
            $output .= '<strong style="text-transform: uppercase; font-size: 11px;">'.htmlspecialchars($type, ENT_QUOTES, 'UTF-8').':</strong> ';
            $output .= '<span>'.$text.'</span>';
            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
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
        $string = self::str($string, false);
        $texts = self::get();
        if (! in_array($string, $texts, true)) {
            self::$msgs[] = ['type' => $type, 'text' => $string];
        }
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
     * Note: When Monolog is active (via Hm_Logger), messages are already logged
     * immediately, so we skip the array dump to prevent duplicate logging.
     * @return bool
     */
    public static function show() {
        // Skip output if Monolog is handling logging
        if (class_exists('Hm_Logger', false)) {
            return true;
        }

        // Legacy fallback: dump all messages as array
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
        // Add to queue (for potential browser display)
        self::self_add($string, $type);

        // Immediately log to Monolog
        if (class_exists('Hm_Logger')) {
            Hm_Logger::getInstance()->log($string, $type);
        }
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
