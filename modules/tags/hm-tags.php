<?php

/**
 * Tags modules
 * @package modules
 * @subpackage tags
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage tags/libs
 */
class Hm_Tags {
    use Hm_Repository;

    private static $data = array();

    public static function init($hmod) {
        self::initRepo('tags', $hmod->user_config, $hmod->session, self::$data);
    }

    public static function registerFolder($tag_id, $serverId, $folder) {
        $tag = self::get($tag_id);
        Hm_Msgs::add('Registering folder: '. json_encode($tag));
        if (isset($tag['server'])) {
            if (isset($tag['server'][$serverId])) {
                if (!in_array($folder, $tag['server'][$serverId])) {
                    $tag['server'][$serverId][] = $folder;
                }
            } else {
                $tag['server'][$serverId] = array($folder);
            }
            Hm_Msgs::add('Tag already exists: '. json_encode($tag));
        } else {
            $tag['server'] = [$serverId => [$folder]];
            Hm_Msgs::add('Tag created: '. json_encode($tag));
        }
        self::edit($tag_id, $tag);
        Hm_Msgs::add('Tag edited: '. json_encode(self::getAll()));
    }

    public function getFolders($tag_id, $serverId) {
        $tag = self::get($tag_id);
        if (isset($tag['server'][$serverId])) {
            return $tag['server'][$serverId];
        }
        return [];
    }
}
