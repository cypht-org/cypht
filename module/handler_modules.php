<?php

abstract class Hm_Handler_Module {
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
        $data['language'] = 'es_ES'; //$this->session->get('language', 'en_US');
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

if (!class_exists('Hm_Handler_login')) {
class Hm_Handler_login extends Hm_Handler_Module {
    public function process($data) {
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
                $servers = $this->session->get('imap_servers', array());
                Hm_Msgs::add('Added server!');
                $this->session->set('imap_servers', array_merge($servers, array(array(
                    'server' => $form['new_imap_server'],
                    'port' => $form['new_imap_port'],
                    'tls' => $tls))));
            }
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_imap_setup_display')) {
class Hm_Handler_imap_setup_display extends Hm_Handler_Module {
    public function process($data) {
        $data['imap_servers'] = array();
        $servers = $this->session->get('imap_servers', array());
        if (!empty($servers)) {
            $data['imap_servers'] = $servers;
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_imap_connect')) {
class Hm_Handler_imap_connect extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['imap_connect'])) {
            list($success, $form) = $this->process_form(array('imap_user', 'imap_pass', 'imap_server_id'));
            if ($success) {
                $servers = $this->session->get('imap_servers', array());
                $details = $servers[$form['imap_server_id']];
                $imap = new Hm_IMAP();
                $imap->connect(array(
                    'username' => $form['imap_user'],
                    'password' => $form['imap_pass'],
                    'server' => $details['server'],
                    'port' => $details['port'],
                    'tls' => $details['tls']
                ));
                $data['imap_debug'] = $imap->show_debug(false, true);
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
                $servers = $this->session->get('imap_servers', array());
                if (isset($servers[$form['imap_server_id']])) {
                    unset($servers[$form['imap_server_id']]);
                    Hm_Msgs::add('Server deleted');
                    $this->session->set('imap_servers', $servers);
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
