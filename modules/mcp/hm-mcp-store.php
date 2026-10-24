<?php

/**
 * Database storage for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Persistent state: user settings, connections, tokens, OAuth clients,
 * MCP sessions, the activity log and rate limit counters
 * @subpackage mcp/lib
 */
class Hm_MCP_Store {

    /* token kinds and their readable prefixes */
    const TOKEN_PREFIXES = [
        'access' => 'cyp_at_',
        'refresh' => 'cyp_rt_',
        'personal' => 'cyp_pat_',
        'runner' => 'cyp_run_',
        'file' => 'cyp_f_',
        'code' => 'cyp_ac_',
        'consent' => 'cyp_ct_',
    ];

    /* connection kinds */
    const CONNECTION_KINDS = ['oauth', 'personal', 'runner'];

    /* site configuration */
    private $site_config;

    /* PDO connection or false */
    private $dbh = false;

    /* time source, replaceable in tests */
    public $clock;

    /**
     * @param object $site_config site configuration
     */
    public function __construct($site_config) {
        $this->site_config = $site_config;
        $this->clock = function () { return time(); };
    }

    /**
     * @return int current unix time
     */
    public function now() {
        return (int) ($this->clock)();
    }

    /**
     * @return PDO|false
     */
    private function db() {
        if (!$this->dbh) {
            $this->dbh = Hm_DB::connect($this->site_config);
        }
        return $this->dbh;
    }

    /**
     * Check that the database and the MCP tables are available
     * @return bool
     */
    public function available() {
        if (!$this->site_config->get('db_driver', false)) {
            return false;
        }
        $dbh = $this->db();
        if (!$dbh) {
            return false;
        }
        try {
            $stmt = $dbh->query('select username from hm_mcp_settings where 1=0');
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $sql query
     * @param array $args parameters
     * @return array|false one row
     */
    private function row($sql, $args) {
        $res = Hm_DB::execute($this->db(), $sql, $args);
        return is_array($res) ? $res : false;
    }

    /**
     * @param string $sql query
     * @param array $args parameters
     * @return array rows
     */
    private function rows($sql, $args) {
        $res = Hm_DB::execute($this->db(), $sql, $args, 'select', true);
        return is_array($res) ? $res : [];
    }

    /**
     * @param string $sql statement
     * @param array $args parameters
     * @return int|false affected rows
     */
    private function exec($sql, $args) {
        $res = Hm_DB::execute($this->db(), $sql, $args, 'modify');
        return $res === false ? false : (int) $res;
    }

    /**
     * @param mixed $value value to encode
     * @return string|null
     */
    private static function enc($value) {
        if ($value === null) {
            return null;
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param string|null $value JSON text
     * @return mixed
     */
    private static function dec($value) {
        if ($value === null || $value === '') {
            return null;
        }
        return json_decode($value, true);
    }

    /* ---------------------------------------------------------------- settings */

    /**
     * Default settings for a user without a stored row
     * @return array
     */
    public static function default_settings() {
        return [
            'enabled' => false,
            'permissions' => Hm_MCP_Permissions::defaults(),
            'accounts' => ['mode' => 'all', 'ids' => []],
            'profile_id' => '',
            'updated_at' => 0,
        ];
    }

    /**
     * Load the settings of a user
     * @param string $username Cypht username
     * @param bool $create store a default row (with a stable profile id) when missing
     * @return array
     */
    public function settings($username, $create = false) {
        $row = $this->row('select enabled, permissions, accounts, profile_id, updated_at from hm_mcp_settings where username=?', [$username]);
        if (!$row) {
            $settings = self::default_settings();
            if ($create) {
                $settings['profile_id'] = Hm_MCP_Crypto::random_id('prf_', 12);
                $this->exec('insert into hm_mcp_settings (username, enabled, permissions, accounts, profile_id, updated_at) values (?, ?, ?, ?, ?, ?)',
                    [$username, 0, self::enc($settings['permissions']), self::enc($settings['accounts']), $settings['profile_id'], $this->now()]);
            }
            return $settings;
        }
        $accounts = self::dec($row['accounts']);
        if (!is_array($accounts) || !in_array($accounts['mode'] ?? '', ['all', 'selected'], true)) {
            $accounts = ['mode' => 'all', 'ids' => []];
        }
        $accounts['ids'] = array_values(array_map('strval', (array) ($accounts['ids'] ?? [])));
        return [
            'enabled' => (bool) $row['enabled'],
            'permissions' => Hm_MCP_Permissions::normalize(self::dec($row['permissions'])),
            'accounts' => $accounts,
            'profile_id' => (string) $row['profile_id'],
            'updated_at' => (int) $row['updated_at'],
        ];
    }

    /**
     * Save the settings of a user
     * @param string $username Cypht username
     * @param array $settings values from settings()
     * @return bool
     */
    public function save_settings($username, $settings) {
        $current = $this->settings($username, true);
        $enabled = array_key_exists('enabled', $settings) ? (bool) $settings['enabled'] : $current['enabled'];
        $permissions = Hm_MCP_Permissions::normalize($settings['permissions'] ?? $current['permissions']);
        $accounts = $settings['accounts'] ?? $current['accounts'];
        $accounts = [
            'mode' => ($accounts['mode'] ?? 'all') === 'selected' ? 'selected' : 'all',
            'ids' => array_values(array_unique(array_map('strval', (array) ($accounts['ids'] ?? [])))),
        ];
        return $this->exec('update hm_mcp_settings set enabled=?, permissions=?, accounts=?, updated_at=? where username=?',
            [$enabled ? 1 : 0, self::enc($permissions), self::enc($accounts), $this->now(), $username]) !== false;
    }

    /* ------------------------------------------------------------- connections */

    /**
     * AAD binding a sealed password to its connection
     * @param string $id connection id
     * @param string $username owner
     * @return string
     */
    private static function password_aad($id, $username) {
        return 'password|'.$id.'|'.$username;
    }

    /**
     * Create a connection
     * @param string $username owner
     * @param string $kind oauth, personal or runner
     * @param string $name label shown to the user
     * @param string $password Cypht login password (sealed with the new key)
     * @param array $options client_id, permissions (null inherits), accounts (null inherits), status
     * @return array [connection id, connection key]
     */
    public function create_connection($username, $kind, $name, $password, $options = []) {
        if (!in_array($kind, self::CONNECTION_KINDS, true)) {
            throw new InvalidArgumentException('Invalid connection kind');
        }
        $id = Hm_MCP_Crypto::random_id('con_', 12);
        $key = Hm_MCP_Crypto::random_key();
        $permissions = array_key_exists('permissions', $options) && is_array($options['permissions'])
            ? Hm_MCP_Permissions::normalize($options['permissions'], array_fill_keys(Hm_MCP_Permissions::keys(), false)) : null;
        $accounts = array_key_exists('accounts', $options) && is_array($options['accounts'])
            ? array_values(array_unique(array_map('strval', $options['accounts']))) : null;
        $res = $this->exec('insert into hm_mcp_connections (id, username, kind, name, client_id, permissions, accounts, sealed_password, sealed_cache, status, created_at, last_used_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $id, $username, $kind, mb_substr((string) $name, 0, 200), $options['client_id'] ?? null,
            self::enc($permissions), self::enc($accounts),
            Hm_MCP_Crypto::seal($password, $key, self::password_aad($id, $username)), null,
            $options['status'] ?? 'active', $this->now(), 0,
        ]);
        if (!$res) {
            throw new RuntimeException('Could not create the connection');
        }
        return [$id, $key];
    }

    /**
     * @param array $row database row
     * @return array connection
     */
    private static function connection_from_row($row) {
        return [
            'id' => $row['id'],
            'username' => $row['username'],
            'kind' => $row['kind'],
            'name' => $row['name'],
            'client_id' => $row['client_id'],
            'permissions' => self::dec($row['permissions']),
            'accounts' => self::dec($row['accounts']),
            'sealed_password' => $row['sealed_password'],
            'sealed_cache' => $row['sealed_cache'],
            'status' => $row['status'],
            'created_at' => (int) $row['created_at'],
            'last_used_at' => (int) $row['last_used_at'],
        ];
    }

    /**
     * @param string $id connection id
     * @return array|false
     */
    public function connection($id) {
        $row = $this->row('select * from hm_mcp_connections where id=?', [$id]);
        return $row ? self::connection_from_row($row) : false;
    }

    /**
     * Connections of a user, excluding pending OAuth authorizations
     * @param string $username owner
     * @param array|null $kinds limit to these kinds
     * @return array
     */
    public function connections($username, $kinds = null) {
        $res = [];
        foreach ($this->rows('select * from hm_mcp_connections where username=? order by created_at', [$username]) as $row) {
            if ($row['status'] === 'pending') {
                continue;
            }
            if ($kinds !== null && !in_array($row['kind'], $kinds, true)) {
                continue;
            }
            $res[] = self::connection_from_row($row);
        }
        return $res;
    }

    /**
     * Update connection fields
     * @param string $id connection id
     * @param array $fields name, permissions, accounts, status, last_used_at, sealed_cache
     * @return bool
     */
    public function update_connection($id, $fields) {
        $sets = [];
        $args = [];
        foreach ($fields as $name => $value) {
            switch ($name) {
                case 'name':
                    $sets[] = 'name=?';
                    $args[] = mb_substr((string) $value, 0, 200);
                    break;
                case 'permissions':
                    $sets[] = 'permissions=?';
                    $args[] = is_array($value) ? self::enc(Hm_MCP_Permissions::normalize($value, array_fill_keys(Hm_MCP_Permissions::keys(), false))) : null;
                    break;
                case 'accounts':
                    $sets[] = 'accounts=?';
                    $args[] = is_array($value) ? self::enc(array_values(array_unique(array_map('strval', $value)))) : null;
                    break;
                case 'status':
                    $sets[] = 'status=?';
                    $args[] = (string) $value;
                    break;
                case 'last_used_at':
                    $sets[] = 'last_used_at=?';
                    $args[] = (int) $value;
                    break;
                case 'sealed_cache':
                    $sets[] = 'sealed_cache=?';
                    $args[] = $value;
                    break;
            }
        }
        if (!$sets) {
            return true;
        }
        $args[] = $id;
        return $this->exec('update hm_mcp_connections set '.implode(', ', $sets).' where id=?', $args) !== false;
    }

    /**
     * Delete a connection with its tokens and sessions
     * @param string $id connection id
     * @param string|null $username owner, checked when not null
     * @return bool true if the connection existed
     */
    public function delete_connection($id, $username = null) {
        $connection = $this->connection($id);
        if (!$connection || ($username !== null && $connection['username'] !== $username)) {
            return false;
        }
        $this->exec('delete from hm_mcp_tokens where connection_id=?', [$id]);
        $this->exec('delete from hm_mcp_sessions where connection_id=?', [$id]);
        $this->exec('delete from hm_mcp_connections where id=?', [$id]);
        return true;
    }

    /**
     * Delete every connection of a user
     * @param string $username owner
     * @return int deleted connections
     */
    public function delete_user_connections($username) {
        $count = 0;
        foreach ($this->rows('select id from hm_mcp_connections where username=?', [$username]) as $row) {
            if ($this->delete_connection($row['id'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Recover the sealed password of a connection
     * @param array $connection connection
     * @param string $key connection key
     * @return string|false
     */
    public function open_password($connection, $key) {
        return Hm_MCP_Crypto::open($connection['sealed_password'], $key, self::password_aad($connection['id'], $connection['username']));
    }

    /**
     * Read the encrypted per connection cache (refreshed OAuth tokens of mail servers)
     * @param array $connection connection
     * @param string $key connection key
     * @return array
     */
    public function open_cache($connection, $key) {
        if (empty($connection['sealed_cache'])) {
            return [];
        }
        $data = Hm_MCP_Crypto::open($connection['sealed_cache'], $key, 'cache|'.$connection['id']);
        $data = $data === false ? null : json_decode($data, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Store the encrypted per connection cache
     * @param array $connection connection
     * @param string $key connection key
     * @param array $data cache values
     * @return bool
     */
    public function save_cache($connection, $key, $data) {
        $sealed = Hm_MCP_Crypto::seal(json_encode($data), $key, 'cache|'.$connection['id']);
        return $this->update_connection($connection['id'], ['sealed_cache' => $sealed]);
    }

    /* ------------------------------------------------------------------ tokens */

    /**
     * AAD binding a wrapped key to its token row
     * @param string $hash token hash
     * @param string $connection_id connection id
     * @param string $kind token kind
     * @return string
     */
    private static function wrap_aad($hash, $connection_id, $kind) {
        return 'token|'.$kind.'|'.$connection_id.'|'.$hash;
    }

    /**
     * Issue a token for a connection
     * @param string $connection_id connection id
     * @param string $kind token kind
     * @param string $key connection key to wrap
     * @param int $ttl lifetime in seconds, 0 for no expiration
     * @param array|null $data extra data stored with the token
     * @return string token value
     */
    public function issue_token($connection_id, $kind, $key, $ttl, $data = null) {
        if (!array_key_exists($kind, self::TOKEN_PREFIXES)) {
            throw new InvalidArgumentException('Invalid token kind');
        }
        $token = Hm_MCP_Crypto::new_token(self::TOKEN_PREFIXES[$kind]);
        $hash = Hm_MCP_Crypto::token_hash($token);
        $now = $this->now();
        $res = $this->exec('insert into hm_mcp_tokens (token_hash, connection_id, kind, wrapped_key, data, expires_at, used_at, created_at) values (?, ?, ?, ?, ?, ?, ?, ?)', [
            $hash, $connection_id, $kind,
            Hm_MCP_Crypto::wrap_key($key, $token, self::wrap_aad($hash, $connection_id, $kind)),
            self::enc($data), $ttl > 0 ? $now + (int) $ttl : 0, 0, $now,
        ]);
        if (!$res) {
            throw new RuntimeException('Could not store the token');
        }
        return $token;
    }

    /**
     * Look up a token
     * @param string $token token value
     * @param array $kinds accepted kinds
     * @param bool $allow_used accept tokens already marked as used
     * @return array|false ['hash', 'kind', 'connection_id', 'data', 'expires_at', 'used_at', 'key']
     */
    public function find_token($token, $kinds, $allow_used = false) {
        if (!is_string($token) || strlen($token) < 20 || strlen($token) > 200) {
            return false;
        }
        $hash = Hm_MCP_Crypto::token_hash($token);
        $row = $this->row('select * from hm_mcp_tokens where token_hash=?', [$hash]);
        if (!$row || !in_array($row['kind'], $kinds, true)) {
            return false;
        }
        if ((int) $row['expires_at'] > 0 && (int) $row['expires_at'] < $this->now()) {
            return false;
        }
        if (!$allow_used && (int) $row['used_at'] > 0) {
            return false;
        }
        $key = Hm_MCP_Crypto::unwrap_key($row['wrapped_key'], $token, self::wrap_aad($hash, $row['connection_id'], $row['kind']));
        if ($key === false) {
            return false;
        }
        return [
            'hash' => $hash,
            'kind' => $row['kind'],
            'connection_id' => $row['connection_id'],
            'data' => self::dec($row['data']),
            'expires_at' => (int) $row['expires_at'],
            'used_at' => (int) $row['used_at'],
            'key' => $key,
        ];
    }

    /**
     * Look up a token row by value without unwrapping its key
     * @param string $token token value
     * @return array|false database row
     */
    public function token_row($token) {
        if (!is_string($token) || $token === '' || strlen($token) > 200) {
            return false;
        }
        return $this->row('select * from hm_mcp_tokens where token_hash=?', [Hm_MCP_Crypto::token_hash($token)]);
    }

    /**
     * Mark a single use token as used. Only one caller can succeed.
     * @param string $hash token hash
     * @return bool true if this call marked the token
     */
    public function mark_token_used($hash) {
        return $this->exec('update hm_mcp_tokens set used_at=? where token_hash=? and used_at=0', [$this->now(), $hash]) === 1;
    }

    /**
     * @param string $hash token hash
     * @return bool
     */
    public function delete_token($hash) {
        return $this->exec('delete from hm_mcp_tokens where token_hash=?', [$hash]) !== false;
    }

    /**
     * Delete tokens of a connection
     * @param string $connection_id connection id
     * @param array|null $kinds limit to these kinds
     * @return void
     */
    public function delete_connection_tokens($connection_id, $kinds = null) {
        if ($kinds === null) {
            $this->exec('delete from hm_mcp_tokens where connection_id=?', [$connection_id]);
            return;
        }
        foreach ($kinds as $kind) {
            $this->exec('delete from hm_mcp_tokens where connection_id=? and kind=?', [$connection_id, $kind]);
        }
    }

    /**
     * Count the live tokens of a connection by kind
     * @param string $connection_id connection id
     * @param string $kind token kind
     * @return int
     */
    public function count_tokens($connection_id, $kind) {
        $row = $this->row('select count(*) as total from hm_mcp_tokens where connection_id=? and kind=? and used_at=0 and (expires_at=0 or expires_at>=?)',
            [$connection_id, $kind, $this->now()]);
        return $row ? (int) $row['total'] : 0;
    }

    /**
     * Expiration of the live tokens of a connection
     * @param string $connection_id connection id
     * @param string $kind token kind
     * @return int|null unix time, 0 for no expiration, null when there is no live token
     */
    public function token_expiry($connection_id, $kind) {
        $rows = $this->rows('select expires_at from hm_mcp_tokens where connection_id=? and kind=? and used_at=0 and (expires_at=0 or expires_at>=?)',
            [$connection_id, $kind, $this->now()]);
        if (!$rows) {
            return null;
        }
        $values = array_map('intval', array_column($rows, 'expires_at'));
        return in_array(0, $values, true) ? 0 : max($values);
    }

    /* ----------------------------------------------------------- oauth clients */

    /**
     * @param array $client client_id, kind, client_name, redirect_uris, metadata
     * @return bool
     */
    public function save_client($client) {
        $now = $this->now();
        $args = [$client['kind'], mb_substr((string) $client['client_name'], 0, 200), self::enc(array_values($client['redirect_uris'])),
            self::enc($client['metadata'] ?? [])];
        if ($this->row('select client_id from hm_mcp_oauth_clients where client_id=?', [$client['client_id']])) {
            return $this->exec('update hm_mcp_oauth_clients set kind=?, client_name=?, redirect_uris=?, metadata=?, last_used_at=? where client_id=?',
                array_merge($args, [$now, $client['client_id']])) !== false;
        }
        return (bool) $this->exec('insert into hm_mcp_oauth_clients (kind, client_name, redirect_uris, metadata, created_at, last_used_at, client_id) values (?, ?, ?, ?, ?, ?, ?)',
            array_merge($args, [$now, 0, $client['client_id']]));
    }

    /**
     * @param string $client_id client id
     * @return array|false
     */
    public function client($client_id) {
        if (!is_string($client_id) || $client_id === '' || strlen($client_id) > 255) {
            return false;
        }
        $row = $this->row('select * from hm_mcp_oauth_clients where client_id=?', [$client_id]);
        if (!$row) {
            return false;
        }
        return [
            'client_id' => $row['client_id'],
            'kind' => $row['kind'],
            'client_name' => $row['client_name'],
            'redirect_uris' => (array) self::dec($row['redirect_uris']),
            'metadata' => (array) self::dec($row['metadata']),
            'created_at' => (int) $row['created_at'],
            'last_used_at' => (int) $row['last_used_at'],
        ];
    }

    /**
     * @param string $client_id client id
     * @return void
     */
    public function touch_client($client_id) {
        $this->exec('update hm_mcp_oauth_clients set last_used_at=? where client_id=?', [$this->now(), $client_id]);
    }

    /* --------------------------------------------------------------- sessions */

    /**
     * @param string $id session id
     * @param string $connection_id owning connection
     * @param int $ttl lifetime in seconds
     * @return string|false session data
     */
    public function session_read($id, $connection_id, $ttl) {
        $row = $this->row('select connection_id, data, updated_at from hm_mcp_sessions where id=?', [$id]);
        if (!$row || $row['connection_id'] !== $connection_id) {
            return false;
        }
        if ((int) $row['updated_at'] < $this->now() - $ttl) {
            $this->session_destroy($id);
            return false;
        }
        return (string) $row['data'];
    }

    /**
     * @param string $id session id
     * @param string $connection_id owning connection
     * @param string $data session data
     * @return bool
     */
    public function session_write($id, $connection_id, $data) {
        $row = $this->row('select connection_id from hm_mcp_sessions where id=?', [$id]);
        if ($row) {
            if ($row['connection_id'] !== $connection_id) {
                return false;
            }
            return $this->exec('update hm_mcp_sessions set data=?, updated_at=? where id=?', [$data, $this->now(), $id]) !== false;
        }
        return (bool) $this->exec('insert into hm_mcp_sessions (id, connection_id, data, updated_at) values (?, ?, ?, ?)',
            [$id, $connection_id, $data, $this->now()]);
    }

    /**
     * @param string $id session id
     * @return bool
     */
    public function session_destroy($id) {
        return $this->exec('delete from hm_mcp_sessions where id=?', [$id]) !== false;
    }

    /**
     * @param int $ttl lifetime in seconds
     * @return array deleted session ids
     */
    public function session_gc($ttl) {
        $cutoff = $this->now() - $ttl;
        $ids = array_column($this->rows('select id from hm_mcp_sessions where updated_at<?', [$cutoff]), 'id');
        if ($ids) {
            $this->exec('delete from hm_mcp_sessions where updated_at<?', [$cutoff]);
        }
        return $ids;
    }

    /* --------------------------------------------------------------- activity */

    /**
     * Record one operation. Never pass message content here.
     * @param array $entry username, connection_id, connection_name, channel, operation, permission, outcome, summary
     * @return bool
     */
    public function log_activity($entry) {
        return (bool) $this->exec('insert into hm_mcp_activity (username, connection_id, connection_name, channel, operation, permission, outcome, summary, created_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            (string) $entry['username'], $entry['connection_id'] ?? null, isset($entry['connection_name']) ? mb_substr((string) $entry['connection_name'], 0, 200) : null,
            mb_substr((string) ($entry['channel'] ?? 'mcp'), 0, 16), mb_substr((string) $entry['operation'], 0, 64),
            isset($entry['permission']) ? mb_substr((string) $entry['permission'], 0, 32) : null,
            mb_substr((string) $entry['outcome'], 0, 16), self::enc($entry['summary'] ?? null), $this->now(),
        ]);
    }

    /**
     * @param string $username owner
     * @param int $limit page size
     * @param int $offset rows to skip
     * @param string|null $connection_id filter by connection
     * @return array
     */
    public function activity($username, $limit = 50, $offset = 0, $connection_id = null) {
        $limit = max(1, min(500, (int) $limit));
        $offset = max(0, (int) $offset);
        $sql = 'select * from hm_mcp_activity where username=?';
        $args = [$username];
        if ($connection_id !== null) {
            $sql .= ' and connection_id=?';
            $args[] = $connection_id;
        }
        $sql .= sprintf(' order by created_at desc, id desc limit %d offset %d', $limit, $offset);
        $res = [];
        foreach ($this->rows($sql, $args) as $row) {
            $row['summary'] = self::dec($row['summary']);
            $row['created_at'] = (int) $row['created_at'];
            $res[] = $row;
        }
        return $res;
    }

    /**
     * Number of activity entries of a user
     * @param string $username owner
     * @param string|null $connection_id filter by connection
     * @return int
     */
    public function count_activity($username, $connection_id = null) {
        $sql = 'select count(*) as total from hm_mcp_activity where username=?';
        $args = [$username];
        if ($connection_id !== null) {
            $sql .= ' and connection_id=?';
            $args[] = $connection_id;
        }
        $row = $this->row($sql, $args);
        return $row ? (int) $row['total'] : 0;
    }

    /**
     * Connections that appear in the activity log of a user, including removed ones
     * @param string $username owner
     * @return array connection id => last known name
     */
    public function activity_connections($username) {
        $res = [];
        $rows = $this->rows('select connection_id, connection_name, max(created_at) as last_seen from hm_mcp_activity where username=? and connection_id is not null group by connection_id, connection_name order by last_seen desc', [$username]);
        foreach ($rows as $row) {
            if (!array_key_exists($row['connection_id'], $res)) {
                $res[$row['connection_id']] = (string) $row['connection_name'];
            }
        }
        return $res;
    }

    /**
     * @param string $username owner
     * @return bool
     */
    public function clear_activity($username) {
        return $this->exec('delete from hm_mcp_activity where username=?', [$username]) !== false;
    }

    /* ------------------------------------------------------------ rate limits */

    /**
     * Count a hit in a fixed window
     * @param string $key limit key, hashed before storage
     * @param int $max allowed hits per window
     * @param int $window window length in seconds
     * @return bool false when the limit is exceeded
     */
    public function rate_limit($key, $max, $window) {
        $key = 'mcp:'.hash('sha256', $key);
        $now = $this->now();
        $row = $this->row('select hits, window_start from hm_mcp_rate_limits where rate_key=?', [$key]);
        if (!$row || (int) $row['window_start'] <= $now - $window) {
            if ($row) {
                $this->exec('update hm_mcp_rate_limits set hits=?, window_start=? where rate_key=?', [1, $now, $key]);
            } else {
                $this->exec('insert into hm_mcp_rate_limits (rate_key, hits, window_start) values (?, ?, ?)', [$key, 1, $now]);
            }
            return 1 <= $max;
        }
        $hits = (int) $row['hits'] + 1;
        $this->exec('update hm_mcp_rate_limits set hits=? where rate_key=?', [$hits, $key]);
        return $hits <= $max;
    }

    /**
     * Take a lock that only one caller can hold until it expires
     * @param string $key lock name, hashed before storage
     * @param int $ttl seconds the lock is held
     * @return bool true if this call took the lock
     */
    public function claim($key, $ttl) {
        $key = 'mcp:'.hash('sha256', 'claim|'.$key);
        $now = $this->now();
        $this->exec('delete from hm_mcp_rate_limits where rate_key=? and window_start<?', [$key, $now - $ttl]);
        /* the primary key lets only one insert succeed */
        return $this->exec('insert into hm_mcp_rate_limits (rate_key, hits, window_start) values (?, ?, ?)', [$key, 1, $now]) === 1;
    }

    /**
     * Let a lock taken with claim() expire
     * @param string $key lock name
     * @param int $ttl lifetime used with claim()
     * @param int $after seconds until the lock can be taken again
     * @return void
     */
    public function release($key, $ttl, $after = 0) {
        $this->exec('update hm_mcp_rate_limits set window_start=? where rate_key=?',
            [$this->now() - $ttl + max(0, (int) $after) - 1, 'mcp:'.hash('sha256', 'claim|'.$key)]);
    }

    /**
     * Reset a rate limit counter
     * @param string $key limit key
     * @return void
     */
    public function rate_reset($key) {
        $this->exec('delete from hm_mcp_rate_limits where rate_key=?', ['mcp:'.hash('sha256', $key)]);
    }

    /* ------------------------------------------------------------- maintenance */

    /* activity entries kept per user, older ones are removed by purge() */
    const MAX_ACTIVITY_PER_USER = 10000;

    /* current limit, changeable in tests */
    public $activity_cap = self::MAX_ACTIVITY_PER_USER;

    /**
     * Run purge() on a fraction of calls, like PHP session garbage collection
     * @param int $activity_days days of activity to keep
     * @param int $session_ttl MCP session lifetime in seconds
     * @param int $divisor run once every $divisor calls on average
     * @return bool true if purge() ran
     */
    public function maybe_purge($activity_days, $session_ttl, $divisor = 50) {
        if ($divisor > 1 && random_int(1, $divisor) !== 1) {
            return false;
        }
        try {
            $this->purge($activity_days, $session_ttl);
        } catch (Exception $e) {
            Hm_Debug::add('MCP purge failed: '.$e->getMessage(), 'warning');
        }
        return true;
    }

    /**
     * Remove expired state
     * @param int $activity_days days of activity to keep
     * @param int $session_ttl MCP session lifetime in seconds
     * @return void
     */
    public function purge($activity_days, $session_ttl) {
        $now = $this->now();
        /* expired tokens, and used single use tokens after a grace period kept for reuse detection */
        $this->exec('delete from hm_mcp_tokens where expires_at>0 and expires_at<?', [$now - 86400]);
        $this->exec('delete from hm_mcp_tokens where used_at>0 and used_at<?', [$now - 86400 * 31]);
        /* authorizations that were never completed */
        foreach ($this->rows('select id from hm_mcp_connections where status=? and created_at<?', ['pending', $now - 1800]) as $row) {
            $this->delete_connection($row['id']);
        }
        $this->session_gc($session_ttl);
        $this->exec('delete from hm_mcp_activity where created_at<?', [$now - 86400 * max(1, $activity_days)]);
        foreach ($this->rows('select username, count(*) as total from hm_mcp_activity group by username', []) as $row) {
            if ((int) $row['total'] <= $this->activity_cap) {
                continue;
            }
            $oldest_kept = $this->row(sprintf('select id from hm_mcp_activity where username=? order by id desc limit 1 offset %d',
                max(0, (int) $this->activity_cap - 1)), [$row['username']]);
            if ($oldest_kept) {
                $this->exec('delete from hm_mcp_activity where username=? and id<?', [$row['username'], (int) $oldest_kept['id']]);
            }
        }
        $this->exec('delete from hm_mcp_rate_limits where window_start<?', [$now - 86400]);
        /* dynamically registered clients that never completed an authorization */
        $this->exec('delete from hm_mcp_oauth_clients where kind=? and last_used_at=0 and created_at<?', ['dcr', $now - 86400 * 7]);
    }
}
