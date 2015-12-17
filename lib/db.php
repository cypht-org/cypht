<?php

/**
 * Database wrapper
 * @package framework
 * @subpackage db
 */

/**
 * DB interface for the framework and modules
 */
class Hm_DB {

    /* DB connection handlers */
    static public $dbh = array();

    /* required DB configuration params */
    static private $required_config = array('db_user', 'db_pass', 'db_name', 'db_host', 'db_driver');

    /* DB config */
    static private $config;

    /**
     * Load DB configuration from the site config
     * @param object $site_config site config
     * @return void
     */
    static private function parse_config($site_config) {
        self::$config = array(
            'db_driver' => $site_config->get('db_driver', false),
            'db_host' => $site_config->get('db_host', false),
            'db_name' => $site_config->get('db_name', false),
            'db_user' => $site_config->get('db_user', false),
            'db_pass' => $site_config->get('db_pass', false),
        );
        foreach (self::$required_config as $v) {
            if (!self::$config[$v]) {
                Hm_Debug::add('Missing configuration setting for %s', $v);
            }
        }
    }

    /**
     * Return a unique key for a DB connection
     * @return string md5 of the DB settings
     */
    static private function db_key() {
        return md5(self::$config['db_driver'].
            self::$config['db_host'].
            self::$config['db_name'].
            self::$config['db_user'].
            self::$config['db_pass']
        );
    }

    /**
     * Connect to a DB server
     * @param object $site_config site settings
     * @return object database connection on success
     */
    static public function connect($site_config) {
        self::parse_config($site_config);
        $key = self::db_key();

        if (array_key_exists($key, self::$dbh) && self::$dbh[$key]) {
            return self::$dbh[$key];
        }
        $dsn = sprintf('%s:host=%s;dbname=%s', self::$config['db_driver'], self::$config['db_host'], self::$config['db_name']);
        try {
            self::$dbh[$key] = new PDO($dsn, self::$config['db_user'], self::$config['db_pass']);
            Hm_Debug::add(sprintf('Connecting to dsn: %s', $dsn));
            return self::$dbh[$key];
        }
        catch (Exception $oops) {
            Hm_Debug::add($oops->getMessage());
            Hm_Msgs::add("An error occurred communicating with the database");
            self::$dbh[$key] = false;
            return false;
        }
    }
}
