<?php

/* base handler module */
abstract class Hm_Handler_Module {

    protected $session = false;
    protected $request = false;
    protected $config = false;
    protected $page = false;
    protected $user_data = false;

    public function __construct($parent, $logged_in) {
        $this->session = $parent->session;
        $this->request = $parent->request;
        $this->config = $parent->config;
        $this->user_config = $parent->user_config;
        $this->page = $parent->page;
    }

    protected function process_form($form, $nonce=false) {
        $post = $this->request->post;
        $success = false;
        $new_form = array();
        foreach($form as $name) {
            if (array_key_exists($name, $post) && (trim($post[$name]) || (($post[$name] === '0' ||  $post[$name] === 0 )))) {
                $new_form[$name] = $post[$name];
            }
        }
        if (count($form) == count($new_form)) {
            $success = true;
        }
        if ($nonce && $success) {
            $success = false;
            if (array_key_exists('hm_nonce', $post)) {
                $key = $this->session->get('username', false);
                if (array_key_exists('hm_id', $this->request->cookie)) {
                    $key .= $this->request->cookie['hm_id'];
                }
                elseif ($this->config->get('enc_key', false)) {
                    $key .= $this->config->get('enc_key', false);
                }
                if (hash_hmac('sha256', $nonce, $key) == $post['hm_nonce']) {
                    $success = true;
                }
            }
            else {
                $success = false;
            }
        }
        return array($success, $new_form);
    }

    abstract public function process($data);
}

/* base output module */
abstract class Hm_Output_Module {

    use Hm_Sanitize;

    protected $lstr = array();
    protected $lang = false;
    protected $nonce_base = false;

    function __construct($input) {
        $this->nonce_base = $input['router_nonce_base'];
    }

    abstract protected function output($input, $format);

    protected function build_nonce($name) {
        return hash_hmac('sha256', $name, $this->nonce_base);
    }
    public function trans($string) {
        if (array_key_exists($string, $this->lstr)) {
            if ($this->lstr[$string] === false) {
                return $string;
            }
            else {
                return $this->lstr[$string];
            }
        }
        else {
            Hm_Debug::add(sprintf('No translation found: %s', $string));
        }
        return $string;
    }

    public function output_content($input, $format, $lang_str) {
        $this->lstr = $lang_str;
        if (array_key_exists('interface_lang', $lang_str)) {
            $this->lang = $lang_str['interface_lang'];
        }
        return $this->output($input, $format);
    }
}

/* module managers */
trait Hm_Modules {

    private static $module_list = array();
    private static $source = false;
    private static $module_queue = array();

    public static function load($mod_list) {
        self::$module_list = $mod_list;
    }

    public static function set_source($source) {
        self::$source = $source;
    }

    public static function add_to_all_pages($module, $logged_in, $marker, $placement, $source) {
        foreach (self::$module_list as $page => $modules) {
            if (!preg_match("/^ajax_/", $page)) {
                self::add($page, $module, $logged_in, $marker, $placement, true, $source);
            }
        }
    }

    public static function add($page, $module, $logged_in, $marker=false, $placement='after', $queue=true, $source=false) {
        $inserted = false;
        if (!array_key_exists($page, self::$module_list)) {
            self::$module_list[$page] = array();
        }
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            Hm_Debug::add(sprintf("Already registered module re-attempted: %s", $module));
            return;
        }
        if (!$source) {
            $source = self::$source;
        }
        if ($marker) {
            $mods = array_keys(self::$module_list[$page]);
            $index = array_search($marker, $mods);
            if ($index !== false) {
                if ($placement == 'after') {
                    $index++;
                }
                $list = self::$module_list[$page];
                self::$module_list[$page] = array_merge(array_slice($list, 0, $index), 
                    array($module => array($source, $logged_in)),
                    array_slice($list, $index));
                $inserted = true;
            }
        }
        else {
            $inserted = true;
            self::$module_list[$page][$module] = array($source, $logged_in);
        }
        if (!$inserted) {
            if ($queue) {
                Hm_Debug::add(sprintf('queueing module %s', $module));
                self::$module_queue[] = array($page, $module, $logged_in, $marker, $placement);
            }
            else {
                Hm_Debug::add(sprintf('failed to insert module %s on %s', $module, $page));
            }
        }
    }

    public static function replace($target, $replacement, $page=false) {
        if ($page && array_key_exists($page, self::$module_list) && array_key_exists($target, self::$module_list[$page])) {
            self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
        }
        else {
            foreach (self::$module_list as $page => $modules) {
                if (array_key_exists($target, $modules)) {
                    self::$module_list[$page] = self::swap_key($target, $replacement, self::$module_list[$page]);
                }
            }
        }
    }

    public static function swap_key($target, $replacement, $modules) {
        $keys = array_keys($modules);
        $values = array_values($modules);
        $size = count($modules);
        for ($i = 0; $i < $size; $i++) {
            if ($keys[$i] == $target) {
                $keys[$i] = $replacement;
                $values[$i][0] = self::$source;
                break;
            }
        }
        return array_combine($keys, $values);
    }

    public static function try_queued_modules() {
        foreach (self::$module_queue as $vals) {
            self::add($vals[0], $vals[1], $vals[2], $vals[3], $vals[4], false);
        }
    }

    public static function del($page, $module) {
        if (array_key_exists($page, self::$module_list) && array_key_exists($module, self::$module_list[$page])) {
            unset(self::$module_list[$page][$module]);
        }
    }

    public static function get_for_page($page) {
        $res = array();
        if (array_key_exists($page, self::$module_list)) {
            $res = array_merge($res, self::$module_list[$page]);
        }
        return $res;
    }

    public static function dump() {
        return self::$module_list;
    }
}

class Hm_Handler_Modules { use Hm_Modules; }
class Hm_Output_Modules { use Hm_Modules; }

function handler_source($source) {
    Hm_Handler_Modules::set_source($source);
}

function output_source($source) {
    Hm_Output_Modules::set_source($source);
}

function replace_module($type, $target, $replacement, $page=false) {
    if ($type == 'handler') {
        Hm_Handler_Modules::replace($target, $replacement, $page);
    }
    elseif ($type == 'output') {
        Hm_Output_Modules::replace($target, $replacement, $page);
    }
}

function add_handler($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Handler_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}
function add_output($page, $mod, $logged_in, $source=false, $marker=false, $placement='after', $queue=true) {
    Hm_Output_Modules::add($page, $mod, $logged_in, $marker, $placement, $queue, $source);
}
function add_module_to_all_pages($type, $mod, $logged_in, $source, $marker, $placement) {
    if ($type == 'output') {
        Hm_Output_Modules::add_to_all_pages($mod, $logged_in, $marker, $placement, $source);
    }
    elseif ( $type == 'handler') {
        Hm_Handler_Modules::add_to_all_pages($mod, $logged_in, $marker, $placement, $source);
    }
}

function setup_base_page($name, $source=false) {
    add_handler($name, 'create_user', false, $source);
    add_handler($name, 'login', false, $source);
    add_handler($name, 'load_user_data', true, $source);
    add_handler($name, 'default_page_data', true, $source);
    add_handler($name, 'message_list_type', true);
    add_handler($name, 'language',  true, $source);
    add_handler($name, 'title', true, $source);
    add_handler($name, 'date', true, $source);
    add_handler($name, 'save_user_data', true, $source);
    add_handler($name, 'logout', true, $source);
    add_handler($name, 'http_headers', true, $source);
    add_output($name, 'header_start', false, $source);
    add_output($name, 'js_data', true, $source);
    add_output($name, 'header_css', false, $source);
    add_output($name, 'header_content', false, $source);
    add_output($name, 'header_end', false, $source);
    add_output($name, 'content_start', false, $source);
    add_output($name, 'login', false, $source);
    add_output($name, 'two_col_layout_start', true, $source);
    add_output($name, 'loading_icon', true, $source);
    add_output($name, 'date', true, $source);
    add_output($name, 'msgs', false, $source);
    add_output($name, 'folder_list_start', true, $source);
    add_output($name, 'folder_list_end', true, $source);
    add_output($name, 'content_section_start', true, $source);
    add_output($name, 'content_section_end', true, $source);
    add_output($name, 'two_col_layout_end', true, $source);
    add_output($name, 'page_js', true, $source);
    add_output($name, 'content_end', true, $source);
}

?>
