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

    public static function addMessage($tagId, $serverId, $folder, $messageId) {
        $folders = self::getFolders($tagId, $serverId);
        if (!in_array($folder, $folders)) {
            self::registerFolder($tagId, $serverId, $folder);
            $folders = self::getFolders($tagId, $serverId);
        }
        if (!isset($folders[$folder])) {
            $folders[$folder] = [];
        }
        if (!in_array($messageId, $folders[$folder])) {
            $folders[$folder][] = $messageId;
        }
        $tag = self::get($tagId);
        $tag['server'][$serverId][$folder] = $folders[$folder];
        return self::edit($tagId, $tag);
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

    private static function getTagIdsWithMessage($messageId) {
        $tags = self::getAll();
        $tagIds = [];
        foreach ($tags as $tagId => $tag) {
            foreach ($tag['server'] as $serverId => $folders) {
                foreach ($folders as $messages) {
                    // Exclude folder indentifiers
                    if (! is_array($messages)) {
                        continue;
                    }
                    if (in_array($messageId, $messages)) {
                        $tagIds[] = $tagId;
                    }
                }
            }
        }
        return $tagIds;
    }

    public static function moveMessageToADifferentFolder($params) {
        $oldId = $params['oldId'];
        $newId = $params['newId'];
        $oldFolder = $params['oldFolder'];
        $newFolder = $params['newFolder'];
        $oldServer = $params['oldServer'];
        $newServer = $params['newServer'] ?? '';

        $tagIds = self::getTagIdsWithMessage($oldId);
        foreach ($tagIds as $tagId) {
            $tag = self::get($tagId);
            $folders = $tag['server'][$oldServer];
            $messages = $folders[$oldFolder];
            $newMessages = [];
            foreach ($messages as $messageId) {
                if ($messageId == $oldId) {
                    continue;
                }
                $newMessages[] = $messageId;
            }
            $folders[$oldFolder] = $newMessages;
            if (!isset($folders[$newFolder])) {
                $folders[$newFolder] = [];
            }
            $folders[$newFolder][] = $newId;
            $tag['server'][$oldServer] = $folders;
            Hm_Msgs::add('Moving message from old id'.$oldId.' to '.$newId.' in tag '.$tag['name']);
            self::edit($tagId, $tag);
        }
    }
}
