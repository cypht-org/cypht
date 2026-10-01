<?php

/**
 * Bearer token authentication for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * The authenticated user and connection behind a request
 * @subpackage mcp/lib
 */
class Hm_MCP_Principal {

    /* Cypht username */
    public $username;

    /* connection row from Hm_MCP_Store */
    public $connection;

    /* connection key unwrapped with the token */
    public $key;

    /* token kind that authenticated the request */
    public $token_kind;

    /* user settings from Hm_MCP_Store */
    public $settings;

    /* effective permissions: global limit combined with the connection */
    public $permissions;

    /**
     * @param array $connection connection row
     * @param string $key connection key
     * @param string $token_kind token kind
     * @param array $settings user settings
     */
    public function __construct($connection, $key, $token_kind, $settings) {
        $this->connection = $connection;
        $this->username = $connection['username'];
        $this->key = $key;
        $this->token_kind = $token_kind;
        $this->settings = $settings;
        $this->permissions = Hm_MCP_Permissions::effective($settings['permissions'], $connection['permissions']);
    }

    /**
     * @param string $permission permission key
     * @return bool
     */
    public function can($permission) {
        return !empty($this->permissions[$permission]);
    }

    /**
     * @return string connection id
     */
    public function connection_id() {
        return $this->connection['id'];
    }

    /**
     * @return string connection label
     */
    public function connection_name() {
        return $this->connection['name'];
    }

    /**
     * Account ids this request may use
     * @param array $all_ids every configured account id
     * @return array
     */
    public function allowed_accounts($all_ids) {
        return Hm_MCP_Permissions::effective_accounts($this->settings['accounts'], $this->connection['accounts'], $all_ids);
    }
}

/**
 * Resolves bearer tokens to principals
 * @subpackage mcp/lib
 */
class Hm_MCP_Auth {

    /* seconds between last used timestamp updates */
    const TOUCH_INTERVAL = 60;

    private $store;
    private $config;

    /**
     * @param Hm_MCP_Store $store storage
     * @param Hm_MCP_Config $config settings
     */
    public function __construct($store, $config) {
        $this->store = $store;
        $this->config = $config;
    }

    /**
     * Authenticate a bearer token
     * @param string $token bearer token
     * @param array $kinds accepted token kinds
     * @return array ['ok' => bool, 'status' => int, 'error' => string, 'message' => string, 'principal' => Hm_MCP_Principal|null]
     */
    public function authenticate($token, $kinds) {
        if (!$this->store->available()) {
            return self::fail(503, 'unavailable', 'The MCP storage is not available.');
        }
        $found = $this->store->find_token($token, $kinds);
        if (!$found) {
            return self::fail(401, 'invalid_token', 'The access token is invalid or expired.');
        }
        if ($found['kind'] === 'access' && (($found['data']['resource'] ?? '') !== $this->config->resource())) {
            return self::fail(401, 'invalid_token', 'The access token was not issued for this server.');
        }
        $connection = $this->store->connection($found['connection_id']);
        if (!$connection || $connection['status'] === 'pending') {
            return self::fail(401, 'invalid_token', 'The access token is invalid or expired.');
        }
        if ($connection['status'] === 'reauth') {
            return self::fail(401, 'invalid_token', 'This connection must be authorized again because the Cypht password changed.');
        }
        $settings = $this->store->settings($connection['username']);
        if (!$settings['enabled']) {
            return self::fail(403, 'access_disabled', 'API and MCP access is turned off in the Cypht settings of this account.');
        }
        $now = $this->store->now();
        if ($connection['last_used_at'] < $now - self::TOUCH_INTERVAL) {
            $this->store->update_connection($connection['id'], ['last_used_at' => $now]);
            $connection['last_used_at'] = $now;
        }
        return ['ok' => true, 'status' => 200, 'error' => '', 'message' => '',
            'principal' => new Hm_MCP_Principal($connection, $found['key'], $found['kind'], $settings),
            'token' => ['kind' => $found['kind'], 'data' => $found['data'], 'expires_at' => $found['expires_at']]];
    }

    /**
     * @param int $status HTTP status
     * @param string $error error code
     * @param string $message description
     * @return array
     */
    private static function fail($status, $error, $message) {
        return ['ok' => false, 'status' => $status, 'error' => $error, 'message' => $message, 'principal' => null];
    }
}
