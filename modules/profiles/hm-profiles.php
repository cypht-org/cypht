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

    use Hm_Repository;

    private static $data = array();

    public static function init($hmod) {
        self::initRepo('profiles', $hmod->user_config, $hmod->session, self::$data);
        if (self::count() == 0) {
            if (PHP_VERSION_ID < 70000) {
                try {
                    self::loadLegacy($hmod);
                } catch (Exception $e) {
                    self::$data = array();
                }
            }
            if (PHP_VERSION_ID >= 70000) {
                try {
                    self::loadLegacy($hmod);
                } catch (Throwable $e) {
                    self::$data = array();
                }
            }
        }
        if (self::count() == 0) {
            self::createDefault($hmod);
        }
    }

    public static function setDefault($id) {
        if (! array_key_exists($id, self::$data)) {
            return false;
        }
        foreach (self::$data as $p_id => $vals) {
            if ($vals['default']) {
                $vals['default'] = false;
                self::edit($p_id, $vals);
            }
        }
        $vals = self::get($id);
        $vals['default'] = true;
        self::edit($id, $vals);
        return true;
    }

    public static function createDefault($hmod) {
        if (! $hmod->module_is_supported('imap') || ! $hmod->module_is_supported('smtp')) {
            return;
        }
        if (! $hmod->config->get('autocreate_profile')) {
            return;
        }
        $imap_servers = Hm_IMAP_List::dump();
        $smtp_servers = Hm_SMTP_List::dump();
        if (count($imap_servers) == 1 && count($smtp_servers) == 1) {
            $imap_server = reset($imap_servers);
            $smtp_server = reset($smtp_servers);
            list($address, $reply_to) = outbound_address_check($hmod, $imap_server['user'], '');
            self::add(array(
                'default' => true,
                'name' => 'Default',
                'address' => $address,
                'replyto' => $reply_to,
                'smtp_id' => $smtp_server['id'],
                'sig' => '',
                'rmk' => '',
                'type' => 'imap',
                'autocreate' => true,
                'user' => $imap_server['user'],
                'server' => $imap_server['server'],
            ));
        }
    }

    public static function loadLegacy($hmod) {
        if ($hmod->module_is_supported('imap')) {
            foreach (Hm_IMAP_List::dump() as $id => $server) {
                $profile = $hmod->user_config->get('profile_imap_'.$server['server'].'_'.$server['user'], array(
                    'profile_default' => false, 'profile_name' => '', 'profile_address' => '',
                    'profile_replyto' => '', 'profile_smtp' => '', 'profile_sig' => '', 'profile_rmk' => ''));
                if (! $profile['profile_name']) {
                    continue;
                }
                self::add(array(
                    'default' => $profile['profile_default'],
                    'name' => $profile['profile_name'],
                    'address' => array_key_exists('profile_address', $profile) ? $profile['profile_address'] : '',
                    'replyto' => $profile['profile_replyto'],
                    'smtp_id' => $profile['profile_smtp'],
                    'sig' => $profile['profile_sig'],
                    'rmk' => $profile['profile_rmk'],
                    'type' => 'imap',
                    'user' => $server['user'],
                    'server' => $server['server'],
                ));
            }
        }
    }

    public static function search($fld, $value) {
        $res = array();
        foreach (self::getAll() as $profile) {
            if (!empty($profile[$fld]) && $profile[$fld] == $value) {
                $res[] = $profile;
            }
        }
        return $res;
    }
}
