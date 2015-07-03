<?php

/**
 * Cache structures
 * @package framework
 * @subpackage cache
 */

/**
 * Used to cache HTML5 formatted sections of a page
 * @package framework
 * @subpackage cache
 */
class Hm_Page_Cache {

    /* holds the cached pages */
    private static $pages = array();

    /**
     * Add a page
     * @param string $key key to access the page data
     * @param string $page data to cache
     * @param bool $save flag used to cache between logins
     * @return void
     */
    public static function add($key, $page, $save=false) {
        self::$pages[$key] = array($page, $save);
    }

    /**
     * Concatenate new cache data to an existing page
     * @param string $key key to access the page data
     * @param string $page data to cache
     * @param bool $save flag used to cache between logins
     * @param string $delim delimiter used between values
     * @return void
     */
    public static function concat($key, $page, $save = false, $delim=false) {
        if (array_key_exists($key, self::$pages)) {
            if ($delim) {
                self::$pages[$key][0] .= $delim.$page;
            }
            else {
                self::$pages[$key][0] .= $page;
            }
        }
        else {
            self::$pages[$key] = array($page, $save);
        }
    }

    /**
     * Delete a page from the cache
     * @param string $key key name of the data to delete
     * @return bool true on success
     */
    public static function del($key) {
        if (array_key_exists($key, self::$pages)) {
            unset(self::$pages[$key]);
            return true;
        }
        return false;
    }

    /**
     * Fetch a cached value from the list
     * @param string $key key name of the data to fetch
     * @return mixed string value on success, bool false on failure
     */
    public static function get($key) {
        if (array_key_exists($key, self::$pages)) {
            Hm_Debug::add(sprintf("PAGE CACHE: %s", $key));
            return self::$pages[$key][0];
        }
        return false;
    }

    /**
     * Return all cached values
     * @return array list of cached values
     */
    public static function dump() {
        return self::$pages;
    }

    /**
     * Remove all cached values
     * @param object $session session interface
     * @return void
     */
    public static function flush($session) {
        self::$pages = array();
        $session->set('page_cache', array());
        $session->set('saved_pages', array());
    }

    /**
     * Load cached pages from session data
     * @param object $session session interface
     * @return void
     */
    public static function load($session) {
        self::$pages = $session->get('page_cache', array());
        self::$pages = array_merge(self::$pages, $session->get('saved_pages', array()));
    }

    /**
     * Save the page cache in the session
     * @param object $session session interface
     * @return void
     */
    public static function save($session) {
        $pages = self::$pages;
        $saved_pages = array();
        foreach (self::$pages as $key => $page) {
            if ($page[1]) {
                $saved_pages[$key] = $pages[$key];
                unset($pages[$key]);
            }
        }
        $session->set('page_cache', $pages);
        $session->set('saved_pages', $saved_pages);
    }
}

/**
 * Helper struct to provide data sources the don't track messages read or flagged state
 * (like POP3 or RSS) with an alternative.
 * @package framework
 * @subpackage cache
 */
trait Hm_Uid_Cache {

    /* UID list */
    private static $read = array();
    private static $unread = array();

    /* Load UIDs from an outside source
     * @param array $data list of uids
     * @return void
     */
    public static function load($data) {
        if (is_array($data) && count($data) == 2) {
            self::$read = array_combine($data[0], array_fill(0, count($data[0]), 0));
            self::$unread = array_combine($data[1], array_fill(0, count($data[1]), 0));
        }
    }

    /**
     * Determine if a UID has been unread
     * @param string $uid UID to search for
     * @return bool true if te UID exists
     */
    public static function is_unread($uid) {
        return array_key_exists($uid, self::$unread);
    }

    /**
     * Determine if a UID has been read
     * @param string $uid UID to search for
     * @return bool true if te UID exists
     */
    public static function is_read($uid) {
        return array_key_exists($uid, self::$read);
    }

    /**
     * Return all the UIDs
     * @return array list of known UIDs
     */
    public static function dump() {
        return array(array_keys(self::$read), array_keys(self::$unread));
    }

    /**
     * Add a UID to the unread list 
     * @param string $uid uid to add
     */
    public static function unread($uid) {
        self::$unread[$uid] = 0;
        if (array_key_exists($uid, self::$read)) {
            unset(self::$read[$uid]);
        }
    }

    /**
     * Add a UID to the read list 
     * @param string $uid uid to add
     */
    public static function read($uid) {
        self::$read[$uid] = 0;
        if (array_key_exists($uid, self::$unread)) {
            unset(self::$unread[$uid]);
        }
    }
}
