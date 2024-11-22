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
        if (isset($tag['server'])) {
            if (isset($tag['server'][$serverId])) {
                if (!in_array($folder, $tag['server'][$serverId])) {
                    $tag['server'][$serverId][] = $folder;
                }
            } else {
                $tag['server'][$serverId] = array($folder);
            }
        } else {
            $tag['server'] = [$serverId => [$folder]];
        }
        self::edit($tag_id, $tag);
    }

    public static function getFolders($tag_id, $serverId) {
        $tag = self::get($tag_id);
        if (isset($tag['server'][$serverId])) {
            return $tag['server'][$serverId];
        }
        return [];
    }
}
