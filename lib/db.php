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
        self::$config = array('db_driver' => $site_config->get('db_driver', false),
            'db_host' => $site_config->get('db_host', false),
            'db_name' => $site_config->get('db_name', false),
            'db_user' => $site_config->get('db_user', false),
            'db_pass' => $site_config->get('db_pass', false),
            'db_socket' => $site_config->get('db_socket', false),
            'db_conn_type' => $site_config->get('db_connection_type', 'host'),
            'db_port' => $site_config->get('db_port', false),
        );
        foreach (self::$required_config as $v) {
            if (!self::$config[$v]) {
                Hm_Debug::add(sprintf('Missing configuration setting for %s', $v));
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
            self::$config['db_port'].
            self::$config['db_name'].
            self::$config['db_user'].
            self::$config['db_pass'].
            self::$config['db_conn_type'].
            self::$config['db_socket']
        );
    }

    /**
     * Build a DSN to connect to the db with
     * @return string
     */
    static public function build_dsn() {
        if (self::$config['db_driver'] == 'sqlite') {
            return sprintf('%s:%s', self::$config['db_driver'], self::$config['db_socket']);
        }
        if (self::$config['db_conn_type'] == 'socket') {
            return sprintf('%s:unix_socket=%s;dbname=%s', self::$config['db_driver'], self::$config['db_socket'], self::$config['db_name']);
        }
        else {
            if (self::$config['db_port']) {
                return sprintf('%s:host=%s;port=%s;dbname=%s', self::$config['db_driver'], self::$config['db_host'], self::$config['db_port'], self::$config['db_name']);
            }
            else {
                return sprintf('%s:host=%s;dbname=%s', self::$config['db_driver'], self::$config['db_host'], self::$config['db_name']);
            }
        }
    }

    /**
     * @param object|false $dbh PDO connection object
     * @param string $sql sql with placeholders to execute
     * @param array $args values to insert into the sql
     * @param bool $type optional type of sql query
     * @param bool $all optional flag to return multiple rows
     * @return boolean|integer|array
     */
    static public function execute($dbh, $sql, $args, $type=false, $all=false) {
        if (!$dbh) {
            return false;
        }
        if (!$type) {
            $type = self::execute_type($sql);
        }
        $sql = $dbh->prepare($sql);
        if (!$sql || !$sql->execute($args)) {
            return false;
        }
        if ($type == 'modify' || $type == 'insert') {
            return $sql->rowCount();
        }
        if ($all) {
            return $sql->fetchAll(PDO::FETCH_ASSOC);
        }
        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $sql query string
     * @return string
     */
    static private function execute_type($sql) {
        switch(substr($sql, 0, 1)) {
            case 'd':
            case 'u':
            case 'i':
                return 'modify';
            case 's':
            default:
                return 'select';
        }
    }

    /**
     * Connect to a DB server
     * @param object $site_config site settings
     * @return object|false database connection on success
     */
    static public function connect($site_config) {
        self::parse_config($site_config);
        $key = self::db_key();

        if (array_key_exists($key, self::$dbh) && self::$dbh[$key]) {
            return self::$dbh[$key];
        }
        $dsn = self::build_dsn();
        try {
            self::$dbh[$key] = new PDO($dsn, self::$config['db_user'], self::$config['db_pass']);
            self::$dbh[$key]->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            Hm_Debug::add(sprintf('Connecting to dsn: %s', $dsn));
            return self::$dbh[$key];
        }
        catch (Exception $oops) {
            Hm_Debug::add($oops->getMessage());
            self::$dbh[$key] = false;
            return false;
        }
    }
}
