<?php

/**
 * Profile modules
 * @package modules
 * @subpackage profiles
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage profiles/lib
 */
class Hm_Profiles {

    private $data = array();

    public function __construct($hmod) {
        $this->load($hmod);
    }

    public function load($hmod) {
        $this->load_new($hmod);
        if (count($this->data) == 0) {
            $this->load_legacy($hmod);
        }
        if (count($this->data) == 0) {
            $this->create_default($hmod);
        }
    }

    public function load_new($hmod) {
        $profiles = $hmod->user_config->get('profiles', array());
        foreach ($profiles as $profile) {
            $this->data[] = $profile;
        }
    }

    public function save($user_config) {
        $user_config->set('profiles', $this->data);
    }

    public function add($data) {
        $this->data[] = $data;
    }

    public function set_default($id) {
        if (!array_key_exists($id, $this->data)) {
            return false;
        }
        foreach ($this->data as $p_id => $vals) {
            if ($vals['default']) {
                $this->data[$p_id]['default'] = false;
            }
        }
        $this->data[$id]['default'] = true;
        return true;
    }

    public function create_default($hmod) {
        if (!$hmod->module_is_supported('imap') || !$hmod->module_is_supported('smtp')) {
            return;
        }
        if (!$hmod->config->get('autocreate_profile')) {
            return;
        }
        $imap_servers = Hm_IMAP_List::dump();
        $smtp_servers = Hm_SMTP_List::dump();
        list($address, $reply_to) = outbound_address_check($hmod, $imap_servers[0]['user'], '');
        if (count($imap_servers) == 1 && count($smtp_servers) == 1) {
            $this->data[] = array(
                'default' => true,
                'name' => 'Default',
                'address' => $address,
                'replyto' => $reply_to,
                'smtp_id' => 0,
                'sig' => '',
                'type' => 'imap',
                'autocreate' => true,
                'user' => $imap_servers[0]['user'],
                'server' => $imap_servers[0]['server'],
            );
        }
    }

    public function load_legacy($hmod) {
        $profiles = array();
        if ($hmod->module_is_supported('imap')) {
            foreach (Hm_IMAP_List::dump() as $id => $server) {
                $profile = $hmod->user_config->get('profile_imap_'.$server['server'].'_'.$server['user'], array(
                    'profile_default' => false, 'profile_name' => '', 'profile_address' => '',
                    'profile_replyto' => '', 'profile_smtp' => '', 'profile_sig' => ''));
                if (!$profile['profile_name']) {
                    continue;
                }
                $profiles[] = array(
                    'default' => $profile['profile_default'],
                    'name' => $profile['profile_name'],
                    'address' => array_key_exists('profile_address', $profile) ? $profile['profile_address'] : '',
                    'replyto' => $profile['profile_replyto'],
                    'smtp_id' => $profile['profile_smtp'],
                    'sig' => $profile['profile_sig'],
                    'type' => 'imap',
                    'user' => $server['user'],
                    'server' => $server['server'],
                );
            }
        }
        $this->data = $profiles;
    }

    public function edit($id, $data) {
        if (array_key_exists($id, $this->data)) {
            $this->data[$id] = $data;
            return true;
        }
        return false;
    }

    public function del($id) {
        if (array_key_exists($id, $this->data)) {
            unset($this->data[$id]);
            return true;
        }
        return false;

    }

    public function get($id) {
        if (array_key_exists($id, $this->data)) {
            return $this->data[$id];
        }
        return false;
    }

    public function list_all() {
        return $this->data;
    }
}
