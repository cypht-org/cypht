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
        $this->data = $hmod->user_config->get('profiles', array());
        if (count($this->data) == 0) {
            $this->load_legacy($hmod);
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
        foreach ($this->data as $id => $vals) {
            if ($vals['default']) {
                $this->data[$id]['default'] = false;
            }
        }
        $this->data[$id]['default'] = true;
        return true;
    }

    public function load_legacy($hmod) {
        $profiles = array();
        if ($hmod->module_is_supported('imap')) {
            foreach (Hm_IMAP_List::dump() as $id => $server) {
                $profile = $hmod->user_config->get('profile_imap_'.$server['server'].'_'.$server['user'], array(
                    'profile_default' => false, 'profile_name' => '', 'profile_address' => '',
                    'profile_replyto' => '', 'profile_smtp' => '', 'profile_sig' => ''));
                $profiles[] = array(
                    'default' => $profile['profile_default'],
                    'name' => $profile['profile_name'],
                    'address' => $profile['profile_address'],
                    'replyto' => $profile['profile_replyto'],
                    'smtp_id' => $profile['profile_smtp'],
                    'sig' => $profile['profile_sig'],
                    'type' => 'imap',
                    'user' => $server['user'],
                    'server' => $server['server'],
                );
            }
        }
        if ($hmod->module_is_supported('pop3')) {
            foreach (Hm_POP3_List::dump() as $id => $server) {
                $profile = $hmod->user_config->get('profile_pop3_'.$server['server'].'_'.$server['user'], array(
                    'profile_default' => false, 'profile_name' => '', 'profile_address' => '',
                    'profile_replyto' => '', 'profile_smtp' => '', 'profile_sig' => ''));
                $profiles[] = array(
                    'default' => $profile['profile_default'],
                    'name' => $profile['profile_name'],
                    'address' => $profile['profile_address'],
                    'replyto' => $profile['profile_replyto'],
                    'smtp_id' => $profile['profile_smtp'],
                    'sig' => $profile['profile_sig'],
                    'type' => 'pop3',
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
    public function list_all() {
        return $this->data;
    }
}
