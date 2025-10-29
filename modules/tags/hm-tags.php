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

    public static function removeMessage($messageId, $tagId) {
        $tag = self::get($tagId);
        if (!$tag) {
            return false;
        }
        foreach ($tag['server'] as $serverId => $folders) {
            foreach ($folders as $folder => $messages) {
                $newMessages = array_filter($messages, function($msgId) use ($messageId) {
                    return $msgId != $messageId;
                });
                $tag['server'][$serverId][$folder] = $newMessages;
            }
        }
        return self::edit($tagId, $tag);
    }

    public static function registerFolder($tag_id, $serverId, $folder) {
        $tag = self::get($tag_id);
        if (! isset($tag['server'][$serverId][$folder])) {
            $tag['server'][$serverId][$folder] = [];
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

    public static function getTagIdsWithMessage($messageId) {
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

            if ($newServer) {
                if (!isset($tag['server'][$newServer])) {
                    $tag['server'][$newServer] = [];
                }
                if (!isset($tag['server'][$newServer][$newFolder])) {
                    $tag['server'][$newServer][$newFolder] = [];
                }
                $tag['server'][$newServer][$newFolder][] = $newId;
            } else {
                if (!isset($folders[$newFolder])) {
                    $folders[$newFolder] = [];
                }
                $folders[$newFolder][] = $newId;
                $tag['server'][$oldServer] = $folders;
            }
            self::edit($tagId, $tag);
        }
    }
}
