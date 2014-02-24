<?php

abstract class Hm_Handler_Module {

    use Hm_Sanitize;

    protected $session = false;
    protected $request = false;
    protected $config = false;
    protected $page = false;

    public function __construct($parent, $logged_in, $args) {
        $this->session = $parent->session;
        $this->request = $parent->request;
        $this->config = $parent->config;
        $this->page = $parent->page;
    }

    protected function process_form($form) {
        $post = $this->request->post;
        $success = false;
        $new_form = array();
        foreach($form as $name) {
            if (isset($post[$name]) && (trim($post[$name]) || $post[$name] === 0)) {
                $new_form[$name] = $post[$name];
            }
        }
        if (count($form) == count($new_form)) {
            $success = true;
        }
        return array($success, $new_form);
    }

    abstract public function process($data);
}

if (!class_exists('HM_Handler_http_headers')) {
class Hm_Handler_http_headers extends Hm_Handler_Module {
    public function process($data) {
        if (isset($data['language'])) {
            $data['http_headers'][] = 'Content-Language: '.substr($data['language'], 0, 2);
        }
        return $data;
    }
}
}
if (!class_exists('Hm_Handler_title')) {
class Hm_Handler_title extends Hm_Handler_Module {
    public function process($data) {
        $data['title'] = ucfirst($this->page);
        return $data;
    }
}}

if (!class_exists('Hm_Handler_language')) {
class Hm_Handler_language extends Hm_Handler_Module {
    public function process($data) {
        $data['language'] = $this->session->get('language', 'en_US');
        return $data;
    }
}}

if (!class_exists('Hm_Handler_date')) {
class Hm_Handler_date extends Hm_Handler_Module {
    public function process($data) {
        $data['date'] = date('Y-m-d h:i:s');
        return $data;
    }
}}

if (!class_exists('Hm_Handler_logout')) {
class Hm_Handler_logout extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['logout']) && !$this->session->loaded) {
            $this->session->destroy();
            Hm_Msgs::add('session destroyed on logout');
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_imap_setup')) {
class Hm_Handler_imap_setup extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['submit_server'])) {
            list($success, $form) = $this->process_form(array('new_imap_server', 'new_imap_port'));
            if (!$success) {
                $data['old_form'] = $form;
                Hm_Msgs::add('You must supply a server name and port');
            }
            else {
                $tls = false;
                if (isset($this->request->post['tls'])) {
                    $tls = true;
                }
                if ($con = fsockopen($form['new_imap_server'], $form['new_imap_port'], $errno, $errstr, 2)) {
                    Hm_IMAP_List::add( array(
                        'server' => $form['new_imap_server'],
                        'port' => $form['new_imap_port'],
                        'tls' => $tls));
                    Hm_Msgs::add('Added server!');
                }
                else {
                    Hm_Msgs::add(sprintf('Cound not add server: %s', $this->html_safe($errstr)));
                }
            }
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_save_imap_cache')) {
class Hm_Handler_save_imap_cache extends Hm_Handler_Module {
    public function process($data) {
        $cache = array();
        $servers = Hm_IMAP_List::dump(false,true);
        foreach ($servers as $index => $server) {
            if (is_object($server['object'])) {
                $cache[$index] = $server['object']->dump_cache('gzip');
            }
        }
        if (count($cache) > 0) {
            $this->session->set('imap_cache', $cache);
            Hm_Debug::add(sprintf('Cached data for %d IMAP connections', count($cache)));
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_save_imap_servers')) {
class Hm_Handler_save_imap_servers extends Hm_Handler_Module {
    public function process($data) {
        $servers = Hm_IMAP_List::dump();
        $cache = $this->session->get('imap_cache', array());
        $new_cache = array();
        foreach ($cache as $index => $cache_str) {
            if (isset($servers[$index])) {
                $new_cache[$index] = $cache_str;
            }
        }
        $this->session->set('imap_servers', $servers);
        $this->session->set('imap_cache', $new_cache);
        Hm_IMAP_List::clean_up();
        return $data;
    }
}}

if (!class_exists('Hm_Handler_load_imap_servers')) {
class Hm_Handler_load_imap_servers extends Hm_Handler_Module {
    public function process($data) {
        $servers = $this->session->get('imap_servers', array());
        foreach ($servers as $index => $server) {
            Hm_IMAP_List::add( $server, $index );
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_imap_setup_display')) {
class Hm_Handler_imap_setup_display extends Hm_Handler_Module {
    public function process($data) {
        $data['imap_servers'] = array();
        $servers = Hm_IMAP_List::dump();
        if (!empty($servers)) {
            $data['imap_servers'] = $servers;
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_imap_connect')) {
class Hm_Handler_imap_connect extends Hm_Handler_Module {
    public function process($data) {
        $data['just_saved_credentials'] = false;
        $data['just_forgot_credentials'] = false;
        $remember = false;
        if (isset($this->request->post['imap_remember'])) {
            $remember = true;
        }
        $remembered = false;
        if (isset($this->request->post['imap_connect'])) {
            list($success, $form) = $this->process_form(array('imap_user', 'imap_pass', 'imap_server_id'));
            $imap = false;
            $cache = false;
            $imap_cache = $this->session->get('imap_cache', array());
            if (isset($imap_cache[$form['imap_server_id']])) {
                $cache = $imap_cache[$form['imap_server_id']];
            }
            if ($success) {
                $imap = Hm_IMAP_List::connect( $form['imap_server_id'], $cache, $form['imap_user'], $form['imap_pass'], $remember );
            }
            elseif (isset($form['imap_server_id'])) {
                $imap = Hm_IMAP_List::connect( $form['imap_server_id'], $cache );
                $remembered = true;
            }
            if ($imap) {
                if ($remember) {
                    $data['just_saved_credentials'] = true;
                }
                if (!$remember && $remembered) {
                    Hm_IMAP_List::forget_credentials( $form['imap_server_id'] );
                    $data['just_forgot_credentials'] = true;
                }
                if ($imap->get_state() == 'authenticated') {
                    $data['imap_folders'] = array_map(function($v) { return $this->html_safe($v); }, array_keys($imap->get_folder_list_by_level()));
                    Hm_Msgs::add("Successfully authenticated to the IMAP server!");
                }
                $data['imap_debug'] = $this->html_safe(trim($imap->show_debug(false, true)));
            }
            else {
                Hm_Msgs::add('Username and password are required');
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_imap_delete')) {
class Hm_Handler_imap_delete extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['imap_delete'])) {
            list($success, $form) = $this->process_form(array('imap_server_id'));
            if ($success) {
                $res = Hm_IMAP_List::del($form['imap_server_id']);
                if ($res) {
                    $data['deleted_server_id'] = $form['imap_server_id'];
                    Hm_Msgs::add('Server deleted');
                }
            }
            else {
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}}

?>
