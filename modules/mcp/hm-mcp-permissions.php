<?php

/**
 * Permission model for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Permissions that the user can enable or disable, and their OAuth scopes
 *
 * The user settings define the global limit. Each connection either inherits
 * the global permissions or restricts them further; it can never exceed them.
 * @subpackage mcp/lib
 */
class Hm_MCP_Permissions {

    /**
     * Permission key => [OAuth scope, enabled by default, label, description]
     */
    const CATALOG = [
        'read' => ['mail.read', true, 'Read messages and attachments',
            'List accounts and folders, search and read messages, attachments and contacts'],
        'organize' => ['mail.organize', true, 'Organize messages',
            'Mark as read or flagged, move, archive, mark as junk and snooze'],
        'drafts' => ['mail.drafts', true, 'Write drafts',
            'Create, edit and delete drafts, replies and forwards without sending them'],
        'send' => ['mail.send', false, 'Send and schedule messages',
            'Send drafts or new messages and manage scheduled messages'],
        'trash' => ['mail.trash', false, 'Move messages to the trash',
            'Move messages to the trash and restore them'],
        'delete_permanent' => ['mail.delete', false, 'Delete messages permanently',
            'Permanently delete messages and empty the trash or junk folders'],
        'folders' => ['mail.folders', false, 'Manage folders',
            'Create, rename and delete folders'],
        'tags' => ['mail.tags', false, 'Manage tags',
            'Add and remove Cypht tags on messages'],
        'contacts_write' => ['mail.contacts', false, 'Edit contacts',
            'Add and update local contacts'],
    ];

    /**
     * @return array permission keys
     */
    public static function keys() {
        return array_keys(self::CATALOG);
    }

    /**
     * @return array permission => bool with the default values
     */
    public static function defaults() {
        $res = [];
        foreach (self::CATALOG as $key => $vals) {
            $res[$key] = $vals[1];
        }
        return $res;
    }

    /**
     * @param string $key permission key
     * @return string|false OAuth scope
     */
    public static function scope($key) {
        return self::CATALOG[$key][0] ?? false;
    }

    /**
     * @return array all OAuth scopes
     */
    public static function scopes() {
        return array_values(array_map(function ($vals) { return $vals[0]; }, self::CATALOG));
    }

    /**
     * @param string $key permission key
     * @return string English label
     */
    public static function label($key) {
        return self::CATALOG[$key][2] ?? $key;
    }

    /**
     * @param string $key permission key
     * @return string English description
     */
    public static function description($key) {
        return self::CATALOG[$key][3] ?? '';
    }

    /**
     * Normalize stored or submitted permission values
     * @param mixed $values permission => bool
     * @param array|null $fallback values used for missing keys, defaults() when null
     * @return array permission => bool for every known permission
     */
    public static function normalize($values, $fallback = null) {
        $fallback = $fallback ?? self::defaults();
        $res = [];
        foreach (self::keys() as $key) {
            if (is_array($values) && array_key_exists($key, $values)) {
                $res[$key] = (bool) $values[$key];
            } else {
                $res[$key] = (bool) ($fallback[$key] ?? false);
            }
        }
        return $res;
    }

    /**
     * Combine the global limit with a connection restriction
     * @param array $global permission => bool
     * @param array|null $connection permission => bool, null to inherit
     * @return array permission => bool
     */
    public static function effective($global, $connection) {
        $global = self::normalize($global);
        if (!is_array($connection)) {
            return $global;
        }
        $connection = self::normalize($connection, $global);
        $res = [];
        foreach (self::keys() as $key) {
            $res[$key] = $global[$key] && $connection[$key];
        }
        return $res;
    }

    /**
     * Convert permissions to a space separated OAuth scope string
     * @param array $permissions permission => bool
     * @return string
     */
    public static function to_scope($permissions) {
        $scopes = [];
        foreach (self::normalize($permissions, array_fill_keys(self::keys(), false)) as $key => $enabled) {
            if ($enabled) {
                $scopes[] = self::scope($key);
            }
        }
        return implode(' ', $scopes);
    }

    /**
     * Convert an OAuth scope string to permissions
     * @param string $scope space separated scopes
     * @return array permission => bool, unknown scopes are ignored
     */
    public static function from_scope($scope) {
        $requested = preg_split('/\s+/', trim((string) $scope), -1, PREG_SPLIT_NO_EMPTY);
        $res = [];
        foreach (self::CATALOG as $key => $vals) {
            $res[$key] = in_array($vals[0], $requested, true);
        }
        return $res;
    }

    /**
     * Combine global and connection account restrictions
     * @param array $global ['mode' => 'all'|'selected', 'ids' => []]
     * @param array|null $connection list of account ids, null to inherit
     * @param array $all_ids every configured account id
     * @return array allowed account ids
     */
    public static function effective_accounts($global, $connection, $all_ids) {
        $all_ids = array_values(array_map('strval', $all_ids));
        $allowed = $all_ids;
        if (is_array($global) && ($global['mode'] ?? 'all') === 'selected') {
            $ids = array_map('strval', (array) ($global['ids'] ?? []));
            $allowed = array_values(array_intersect($all_ids, $ids));
        }
        if (is_array($connection)) {
            $ids = array_map('strval', $connection);
            $allowed = array_values(array_intersect($allowed, $ids));
        }
        return $allowed;
    }
}
