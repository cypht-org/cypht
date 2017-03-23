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

    public function __construct() {
    }

    public function load($session) {
    }

    public function save($session) {
    }

    public function add($data) {
    }

    public function load_legacy($hmod) {
        $profiles = array();
        if ($hmod->module_is_supported('imap')) {
            foreach (Hm_IMAP_List::dump() as $id => $server) {
                $details = $hmod->user_config->get('profile_imap_'.$server['server'].'_'.$server['user'], array(
                    'default' => false, 'name' => '', 'address' => '', 'replyto' => '', 'smtp_id' => '', 'sig' => ''));
                $details['type'] = 'imap';
                $details['user'] = $server['user'];
                $details['server'] = $server['server'];
                $profiles[] = $details;
            }
        }
        if ($hmod->module_is_supported('pop3')) {
            foreach (Hm_POP3_List::dump() as $id => $server) {
                $details = $hmod->user_config->get('profile_pop3_'.$server['server'].'_'.$server['user'], array(
                    'default' => false, 'name' => '', 'address' => '', 'replyto' => '', 'smtp_id' => '', 'sig' => ''));
                $details['type'] = 'pop3';
                $details['user'] = $server['user'];
                $details['server'] = $server['server'];
                $profiles[] = $details;
            }
        }
        foreach ($profiles as $p) {
            elog(Hm_IMAP_List::fetch($p['user'], $p['server']));
        }
    }

    public function edit($id, $data) {
    }

    public function delete($id) {
    }
}
