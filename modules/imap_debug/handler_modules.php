<?php

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
                    Hm_Msgs::add(sprintf('Cound not add server: %s', $errstr));
                }
            }
        }
        return $data;
    }
}}

if (!class_exists('Hm_Handler_save_imap_cache')) {
class Hm_Handler_save_imap_cache extends Hm_Handler_Module {
    public function process($data) {
        $cache = $this->session->get('imap_cache', array());
        $servers = Hm_IMAP_List::dump(false, true);
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
                    Hm_Msgs::add("Successfully authenticated to the IMAP server!");
                    $data['imap_folders'] = $imap->get_folder_list_by_level();
                }
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
