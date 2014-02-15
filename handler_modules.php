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

    abstract public function process($data);
}

class Hm_Handler_Module_Title extends Hm_Handler_Module {
    public function process($data) {
        $data['title'] = ucfirst($this->page);
        return $data;
    }
}

class Hm_Handler_Module_Date extends Hm_Handler_Module {
    public function process($data) {
        $data['date'] = date('r');
        return $data;
    }
}

class Hm_Handler_Module_Login extends Hm_Handler_Module {
    public function process($data) {
        return $data;
    }
}

class Hm_Handler_Module_Logout extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['logout']) && !$this->session->loaded) {
            $this->session->destroy();
            Hm_Msgs::add('session destroyed on logout');
        }
        return $data;
    }
}

class Hm_Handler_Module_Imap_setup extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['submit_server'])) {
            if ((!isset($this->request->post['new_imap_server']) || !$this->request->post['new_imap_server'])
                || (!isset($this->request->post['new_imap_port']) || !$this->request->post['new_imap_port'])) {
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
                    'server' => $this->request->post['new_imap_server'],
                    'port' => $this->request->post['new_imap_port'],
                    'tls' => $tls))));

            }
        }
        return $data;
    }
}

class Hm_Handler_Module_Imap_setup_display extends Hm_Handler_Module {
    public function process($data) {
        $data['imap_servers'] = array();
        $servers = $this->session->get('imap_servers', array());
        if (!empty($servers)) {
            $data['imap_servers'] = $servers;
        }
        return $data;
    }
}

class Hm_Handler_Module_Imap_connect extends Hm_Handler_Module {
    public function process($data) {
        $post = $this->request->post;
        if (isset($post['imap_server_id']) && isset($post['imap_user']) && isset($post['imap_pass'])) {
            $servers = $this->session->get('imap_servers', array());
            if (isset($servers[$post['imap_server_id']])) {
                $details = $servers[$post['imap_server_id']];
                $imap = new Hm_IMAP();
                $imap->connect(array(
                    'username' => $post['imap_user'],
                    'password' => $post['imap_pass'],
                    'server' => $details['server'],
                    'port' => $details['port'],
                    'tls' => $details['tls']
                ));
                $data['imap_debug'] = $imap->show_debug(false, true);
            }
        }
        return $data;
    }
}

class Hm_Handler_Module_Imap_delete extends Hm_Handler_Module {
    public function process($data) {
        $post = $this->request->post;
        if (isset($post['imap_server_id']) && isset($post['imap_delete']) && isset($post['imap_server_id'])) {
            $servers = $this->session->get('imap_servers', array());
            if (isset($servers[$post['imap_server_id']])) {
                unset($servers[$post['imap_server_id']]);
                $this->session->set('imap_servers', $servers);
            }
        }
        return $data;
    }
}

?>
