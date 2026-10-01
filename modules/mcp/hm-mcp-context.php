<?php

/**
 * Mail context for MCP and REST requests
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * In memory session used by Cypht libraries during an API request.
 * Nothing is persisted: API requests never create a browser session.
 * @subpackage mcp/lib
 */
class Hm_MCP_Session {

    public $loaded = false;
    public $active = true;
    public $enc_key = '';
    public $session_key = '';
    public $internal_users = false;
    private $data = [];

    /**
     * @param string $name key
     * @param mixed $default value when missing
     * @param bool $user unused, kept for compatibility
     * @return mixed
     */
    public function get($name, $default = false, $user = false) {
        return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
    }

    /**
     * @param string $name key
     * @param mixed $value value
     * @param bool $user unused, kept for compatibility
     * @return void
     */
    public function set($name, $value, $user = false) {
        $this->data[$name] = $value;
    }

    /**
     * @param string $name key
     * @return bool
     */
    public function del($name) {
        unset($this->data[$name]);
        return true;
    }

    public function record_unsaved($value) {}
    public function is_active() { return true; }
    public function is_admin() { return false; }
    public function close_early() { return true; }
    public function end() { return true; }
    public function destroy($request = null) { return true; }
    public function secure_cookie($request, $name, $value, $path = '', $domain = '', $same_site = 'Strict') { return true; }
    public function delete_cookie($request, $name, $path = '', $domain = '') { return true; }
    public function auth($user, $pass) { return false; }
}

/**
 * The mail state of one user for one request: decrypted settings, accounts
 * and connected mailboxes
 * @subpackage mcp/lib
 */
class Hm_MCP_Context {

    /* default time budget for one request, in seconds */
    const TIME_BUDGET = 40;

    /* seconds special folder lookups are reused from the connection cache */
    const SPECIALS_TTL = 21600;

    /* module sets whose libraries the API can use */
    const LIBRARIES = ['core', 'imap', 'smtp', 'profiles', 'contacts', 'local_contacts', 'tags'];

    /* folder roles and the special folder keys Cypht stores for them */
    const ROLES = ['inbox', 'sent', 'drafts', 'trash', 'junk', 'archive', 'all', 'flagged'];

    /* folder names used to find special folders on servers without SPECIAL-USE */
    const ROLE_NAMES = [
        'sent' => ['sent', 'sent items', 'sent messages', 'sent mail', 'enviados', 'elementos enviados'],
        'drafts' => ['drafts', 'draft', 'borradores'],
        'trash' => ['trash', 'deleted items', 'deleted messages', 'bin', 'papelera'],
        'junk' => ['junk', 'spam', 'junk e-mail', 'junk email', 'correo no deseado'],
        'archive' => ['archive', 'archives', 'archivo', 'archivados'],
    ];

    public $site_config;
    public $config;
    public $store;
    public $principal;
    public $user_config;
    public $session;
    public $username;

    /* Cypht libraries expect a cache and request object on handler like objects */
    public $cache = null;
    public $request;

    /* account id => server details without passwords */
    protected $servers = [];

    /* allowed account ids */
    protected $allowed = [];

    /* account id => connected mailbox */
    protected $mailboxes = [];

    /* account id => ['roles' => role => folder, 'scanned' => roles looked up by name, 'time' => unix time] */
    protected $specials = [];

    /* sealed OAuth access token cache of the connection */
    protected $oauth_cache = [];
    protected $cache_dirty = false;

    /* unix time with microseconds when the request must stop starting new work */
    protected $deadline;

    /**
     * @param object $site_config site configuration
     * @param Hm_MCP_Config $config MCP settings
     * @param Hm_MCP_Store $store storage
     * @param Hm_MCP_Principal $principal authenticated principal
     * @param object $user_config decrypted user settings
     * @param object $session in memory session
     * @param array $servers account id => server details
     * @param array $cache decrypted connection cache (OAuth tokens, special folders)
     */
    public function __construct($site_config, $config, $store, $principal, $user_config, $session, $servers, $cache = []) {
        $this->site_config = $site_config;
        $this->config = $config;
        $this->store = $store;
        $this->principal = $principal;
        $this->username = $principal->username;
        $this->user_config = $user_config;
        $this->session = $session;
        $this->servers = $servers;
        $this->oauth_cache = is_array($cache['oauth'] ?? null) ? $cache['oauth'] : [];
        foreach ((array) ($cache['specials'] ?? []) as $id => $entry) {
            if (is_array($entry) && isset($entry['roles'], $entry['time']) && $entry['time'] > time() - self::SPECIALS_TTL) {
                $this->specials[$id] = ['roles' => (array) $entry['roles'], 'scanned' => (array) ($entry['scanned'] ?? []), 'time' => (int) $entry['time']];
            }
        }
        $this->allowed = $principal->allowed_accounts(array_keys($servers));
        $this->deadline = microtime(true) + self::TIME_BUDGET;
        $this->request = (object) ['post' => [], 'get' => [], 'server' => [], 'type' => 'API', 'format' => 'Hm_Format_JSON'];
    }

    /**
     * Load the Cypht libraries the API needs
     * @param object $site_config site configuration
     * @return void
     */
    public static function require_libraries($site_config) {
        $enabled = (array) $site_config->get_modules();
        foreach (self::LIBRARIES as $name) {
            if (($name === 'core' || in_array($name, $enabled, true)) && is_readable(APP_PATH.'modules/'.$name.'/modules.php')) {
                require_once APP_PATH.'modules/'.$name.'/modules.php';
            }
        }
    }

    /**
     * Build the context for a principal
     * @param object $site_config site configuration
     * @param Hm_MCP_Config $config MCP settings
     * @param Hm_MCP_Store $store storage
     * @param Hm_MCP_Principal $principal authenticated principal
     * @return Hm_MCP_Context
     * @throws Hm_MCP_Error
     */
    public static function load($site_config, $config, $store, $principal) {
        self::require_libraries($site_config);
        if (!in_array('imap', (array) $site_config->get_modules(), true)) {
            throw new Hm_MCP_Error('unavailable', 'The IMAP module set is not enabled on this server.');
        }
        $password = $store->open_password($principal->connection, $principal->key);
        if ($password === false) {
            throw new Hm_MCP_Error('reauth_required', 'This connection cannot unlock the account settings. Connect again.');
        }
        $user_config = load_user_config_object($site_config);
        $user_config->load($principal->username, $password);
        if (!empty($user_config->decrypt_failed)) {
            /* the Cypht password changed after the connection was created */
            $store->update_connection($principal->connection_id(), ['status' => 'reauth']);
            throw new Hm_MCP_Error('reauth_required', 'The Cypht password changed. Connect again to restore access.');
        }
        $session = new Hm_MCP_Session();
        $session->set('username', $principal->username);
        $session->set('user_data', $user_config->dump());

        Hm_IMAP_List::init($user_config, $session);
        if ($site_config->get('auth_type') === 'IMAP') {
            self::add_auth_server($site_config, $principal->username, $password);
        }
        if (class_exists('Hm_SMTP_List')) {
            Hm_SMTP_List::init($user_config, $session);
        }
        $cache = $store->open_cache($principal->connection, $principal->key);
        $context = new static($site_config, $config, $store, $principal, $user_config, $session,
            Hm_IMAP_List::dump(), $cache);
        $context->password = $password;
        $context->apply_oauth_cache();
        return $context;
    }

    /* login password, kept for this request only to save changes to the user settings */
    private $password = null;

    /**
     * Change the stored user settings. The latest stored copy is loaded first, so only
     * the requested change is written, never values that only live in this request.
     * Note that a browser session with unsaved changes can still overwrite it later.
     * @param callable $change function(object $user_config): array of changed setting names
     * @return bool true when the settings were saved
     * @throws Hm_MCP_Error
     */
    public function update_user_settings($change) {
        if ($this->password === null) {
            throw new Hm_MCP_Error('unavailable', 'The user settings cannot be changed by this connection.');
        }
        $fresh = load_user_config_object($this->site_config);
        $fresh->load($this->username, $this->password);
        if (!empty($fresh->decrypt_failed)) {
            $this->store->update_connection($this->principal->connection_id(), ['status' => 'reauth']);
            throw new Hm_MCP_Error('reauth_required', 'The Cypht password changed. Connect again to restore access.');
        }
        $changed = $change($fresh);
        if (!$changed) {
            return false;
        }
        if ($fresh->save($this->username, $this->password) === false) {
            throw new Hm_MCP_Error('upstream_error', 'The settings could not be saved.');
        }
        /* keep the copy of this request in sync without saving it */
        $data = $this->user_config->dump();
        foreach ((array) $changed as $name) {
            $data[$name] = $fresh->get($name);
        }
        $this->user_config->reload($data, $this->username);
        $this->session->set('user_data', $data);
        return true;
    }

    /**
     * With IMAP authentication the login server is not stored in the user settings
     * @param object $site_config site configuration
     * @param string $username login name
     * @param string $password login password
     * @return void
     */
    private static function add_auth_server($site_config, $username, $password) {
        list($server, $port, $tls, $sieve, $sieve_tls) = get_auth_config($site_config, 'imap');
        if (!$server) {
            return;
        }
        $details = [
            'name' => $site_config->get('imap_auth_name', $username),
            'default' => true,
            'server' => $server,
            'port' => $port,
            'tls' => $tls,
            'user' => $username,
            'pass' => $password,
            'type' => 'imap',
        ];
        if ($sieve) {
            $details['sieve_config_host'] = $sieve;
            $details['sieve_tls'] = $sieve_tls;
        }
        foreach (Hm_IMAP_List::dump() as $id => $existing) {
            if (!empty($existing['default']) || (($existing['server'] ?? '') === $server && ($existing['user'] ?? '') === $username)) {
                Hm_IMAP_List::edit($id, $details);
                return;
            }
        }
        Hm_IMAP_List::add($details, false);
    }

    /* ----------------------------------------------------------- accounts */

    /**
     * Allowed accounts
     * @param bool $include_hidden include accounts hidden from combined views
     * @return array list of account descriptors
     */
    public function accounts($include_hidden = true) {
        $res = [];
        foreach ($this->allowed as $id) {
            $account = $this->describe($id);
            if ($include_hidden || !$account['hidden']) {
                $res[] = $account;
            }
        }
        return $res;
    }

    /**
     * Find an allowed account by id or email address
     * @param string $ref account id or address
     * @return array account descriptor
     * @throws Hm_MCP_Error
     */
    public function account($ref) {
        $ref = trim((string) $ref);
        if ($ref !== '' && in_array($ref, $this->allowed, true)) {
            return $this->describe($ref);
        }
        $matches = [];
        foreach ($this->allowed as $id) {
            if (strcasecmp((string) ($this->servers[$id]['user'] ?? ''), $ref) === 0) {
                $matches[] = $id;
            }
        }
        if (count($matches) === 1) {
            return $this->describe($matches[0]);
        }
        if (array_key_exists($ref, $this->servers)) {
            throw new Hm_MCP_Error('account_not_allowed', 'This connection is not allowed to use that account.');
        }
        throw new Hm_MCP_Error('not_found', 'Account not found. Use an id returned by list_accounts.');
    }

    /**
     * @param string $id account id
     * @return array account descriptor
     */
    protected function describe($id) {
        $server = $this->servers[$id];
        return [
            'id' => (string) $id,
            'name' => Hm_MCP_Format::text($server['name'] ?? ''),
            'email' => Hm_MCP_Format::text($server['user'] ?? ''),
            'type' => (string) ($server['type'] ?? 'imap'),
            'hidden' => !empty($server['hide']),
            'can_send' => $this->smtp_for($id) !== false,
        ];
    }

    /**
     * SMTP server used to send from an account
     * @param string $id account id
     * @return string|false SMTP server id
     */
    public function smtp_for($id) {
        if (!class_exists('Hm_SMTP_List')) {
            return false;
        }
        foreach ((array) $this->user_config->get('profiles', []) as $profile) {
            if (($profile['imap_id'] ?? null) == $id && !empty($profile['smtp_id']) && Hm_SMTP_List::dump($profile['smtp_id'])) {
                return (string) $profile['smtp_id'];
            }
        }
        $user = $this->servers[$id]['user'] ?? '';
        foreach (Hm_SMTP_List::dump() as $smtp_id => $smtp) {
            if ($user !== '' && strcasecmp((string) ($smtp['user'] ?? ''), $user) === 0) {
                return (string) $smtp_id;
            }
        }
        return false;
    }

    /* ---------------------------------------------------------- mailboxes */

    /**
     * Connected mailbox for an allowed account
     * @param string $id account id or address
     * @param bool $read_only open folders read only (EXAMINE), so reading never marks messages as read
     * @return object Hm_Mailbox
     * @throws Hm_MCP_Error
     */
    public function mailbox($id, $read_only = true) {
        $id = $this->account($id)['id'];
        if (!array_key_exists($id, $this->mailboxes)) {
            $mailbox = $this->connect($id);
            if (!$mailbox || !$mailbox->authed()) {
                throw new Hm_MCP_Error('upstream_error', sprintf('Could not connect to the mail server of account "%s".',
                    $this->servers[$id]['name'] ?? $id), ['account_id' => $id]);
            }
            $this->mailboxes[$id] = ['mailbox' => $mailbox, 'read_only' => null];
        }
        $entry = &$this->mailboxes[$id];
        if ($entry['read_only'] !== $read_only) {
            $entry['mailbox']->set_read_only($read_only);
            $connection = $entry['mailbox']->get_connection();
            if (is_object($connection) && property_exists($connection, 'selected_mailbox')) {
                /* select the folder again with the new access mode */
                $connection->selected_mailbox = false;
            }
            $entry['read_only'] = $read_only;
        }
        return $entry['mailbox'];
    }

    /**
     * Connect to the mail server of an account, refreshing OAuth tokens first
     * @param string $id account id
     * @return object|false Hm_Mailbox
     */
    protected function connect($id) {
        $server = Hm_IMAP_List::dump($id, true);
        if (!$server) {
            return false;
        }
        if (($server['auth'] ?? '') === 'xoauth2') {
            $server['expiration'] = $server['expiration'] ?? 0;
            $result = imap_refresh_oauth2_token($server, $this->site_config);
            if (!empty($result)) {
                Hm_IMAP_List::update_oauth2_token($id, $result[1], $result[0]);
                $this->remember_token('imap', $id, $server, $result[1], $result[0]);
            }
        }
        return Hm_IMAP_List::connect($id);
    }

    /**
     * Store a refreshed OAuth access token in the connection cache
     * @param string $type imap or smtp
     * @param string $id server id
     * @param array $server server details
     * @param string $token access token
     * @param int $expiration unix time
     * @return void
     */
    public function remember_token($type, $id, $server, $token, $expiration) {
        $this->oauth_cache[$type.':'.$id] = [
            'server' => (string) ($server['server'] ?? ''),
            'user' => (string) ($server['user'] ?? ''),
            'pass' => (string) $token,
            'expiration' => (int) $expiration,
        ];
        $this->cache_dirty = true;
    }

    /**
     * Use cached OAuth access tokens that are still valid
     * @return void
     */
    public function apply_oauth_cache() {
        $lists = ['imap' => 'Hm_IMAP_List', 'smtp' => 'Hm_SMTP_List'];
        foreach ($this->oauth_cache as $key => $entry) {
            list($type, $id) = array_pad(explode(':', $key, 2), 2, '');
            $class = $lists[$type] ?? false;
            if (!$class || !class_exists($class) || !is_array($entry) || ($entry['expiration'] ?? 0) < time() + 60) {
                unset($this->oauth_cache[$key]);
                continue;
            }
            $server = $class::dump($id, true);
            if (!$server || ($server['auth'] ?? '') !== 'xoauth2' || ($server['server'] ?? '') !== $entry['server'] ||
                ($server['user'] ?? '') !== $entry['user']) {
                continue;
            }
            $class::update_oauth2_token($id, $entry['pass'], $entry['expiration']);
        }
    }

    /* ------------------------------------------------------------ folders */

    /**
     * Folders with a special role for an account
     * @param string $id account id
     * @param object|null $mailbox connected mailbox, connected when null
     * @param array|null $needed roles to look up by folder name when the server does not
     *                           report them; null looks up every missing role
     * @return array role => folder
     */
    public function special_folders($id, $mailbox = null, $needed = null) {
        $id = $this->account($id)['id'];
        /* folder lookups work in any access mode: keep the mode of an open mailbox */
        $mailbox = $mailbox ?: (isset($this->mailboxes[$id]) ? $this->mailboxes[$id]['mailbox'] : $this->mailbox($id));
        if (!array_key_exists($id, $this->specials)) {
            $res = ['inbox' => 'INBOX'];
            $exposed = $mailbox->get_special_use_mailboxes();
            foreach ((is_array($exposed) ? $exposed : []) as $role => $folder) {
                if (in_array($role, self::ROLES, true) && $folder) {
                    $res[$role] = $folder;
                }
            }
            $configured = $this->configured_special_folders($id);
            foreach (['sent' => 'sent', 'draft' => 'drafts', 'trash' => 'trash', 'archive' => 'archive', 'junk' => 'junk'] as $key => $role) {
                if (!empty($configured[$key])) {
                    $res[$role] = $configured[$key];
                }
            }
            $this->specials[$id] = ['roles' => $res, 'scanned' => [], 'time' => time()];
            $this->cache_dirty = true;
        }
        $entry = &$this->specials[$id];
        $needed = $needed === null ? array_keys(self::ROLE_NAMES) : array_intersect($needed, array_keys(self::ROLE_NAMES));
        $missing = array_diff($needed, array_keys($entry['roles']), $entry['scanned']);
        if ($missing) {
            $folders = $mailbox->get_folders();
            $this->cache_dirty = true;
            foreach ($missing as $role) {
                $entry['scanned'][] = $role;
                foreach ((is_array($folders) ? $folders : []) as $key => $folder) {
                    $base = strtolower((string) ($folder['basename'] ?? $folder['name'] ?? $key));
                    if (in_array($base, self::ROLE_NAMES[$role], true) && empty($folder['noselect'])) {
                        $entry['roles'][$role] = (string) ($folder['id'] ?? $key);
                        break;
                    }
                }
            }
        }
        return $entry['roles'];
    }

    /**
     * Special folders configured by the user in Cypht, with the same matching rules
     * as get_special_folders(): by server and user, then by account id
     * @param string $id account id
     * @return array key (sent, draft, trash, archive, junk) => folder
     */
    protected function configured_special_folders($id) {
        $server = $this->servers[$id] ?? [];
        $specials = $this->user_config->get('special_imap_folders', []);
        if (!is_array($specials)) {
            return [];
        }
        foreach ($specials as $vals) {
            if (is_array($vals) && array_key_exists('imap_user', $vals) && array_key_exists('imap_server', $vals) &&
                $vals['imap_server'] === ($server['server'] ?? null) && $vals['imap_user'] === ($server['user'] ?? null)) {
                return $vals;
            }
        }
        return is_array($specials[$id] ?? null) ? $specials[$id] : [];
    }

    /**
     * Resolve a folder argument, which may be a role name like "sent"
     * @param string $id account id
     * @param string $folder folder id or role
     * @return string folder id
     * @throws Hm_MCP_Error
     */
    public function resolve_folder($id, $folder) {
        $folder = (string) $folder;
        if ($folder === '' || preg_match('/[\r\n\0]/', $folder)) {
            throw new Hm_MCP_Error('invalid_argument', 'The folder is not valid.');
        }
        $role = strtolower($folder);
        if ($role === 'inbox') {
            return 'INBOX';
        }
        if (in_array($role, self::ROLES, true)) {
            $specials = $this->special_folders($id, null, [$role]);
            if (!empty($specials[$role])) {
                return $specials[$role];
            }
            throw new Hm_MCP_Error('not_found', sprintf('This account has no %s folder.', $role));
        }
        return $folder;
    }

    /**
     * Role of a folder, if any
     * @param string $id account id
     * @param string $folder folder id
     * @return string|null
     */
    public function folder_role($id, $folder) {
        if (strtolower($folder) === 'inbox') {
            return 'inbox';
        }
        $specials = $this->special_folders($id, null, []);
        $role = array_search($folder, $specials, true);
        return $role === false ? null : $role;
    }

    /* -------------------------------------------------------------- misc */

    /**
     * @return float seconds left in the time budget
     */
    public function time_left() {
        return $this->deadline - microtime(true);
    }

    /**
     * @param string $name module set name
     * @return bool
     */
    public function module_is_supported($name) {
        return in_array(strtolower($name), (array) $this->site_config->get_modules(), true);
    }

    /**
     * Link to a message in the Cypht web interface
     * @param string $account account id
     * @param string $folder folder id
     * @param string $uid message uid
     * @return string
     */
    public function message_url($account, $folder, $uid) {
        return $this->config->public_url().'/?'.http_build_query([
            'page' => 'message',
            'uid' => $uid,
            'list_path' => sprintf('imap_%s_%s', $account, bin2hex($folder)),
        ]);
    }

    /**
     * Close connections and persist refreshed OAuth tokens
     * @return void
     */
    public function finish() {
        if ($this->cache_dirty) {
            $cache = $this->store->open_cache($this->principal->connection, $this->principal->key);
            $cache['oauth'] = $this->oauth_cache;
            $cache['specials'] = $this->specials;
            $this->store->save_cache($this->principal->connection, $this->principal->key, $cache);
            $this->cache_dirty = false;
        }
        foreach (['Hm_IMAP_List', 'Hm_SMTP_List'] as $class) {
            if (class_exists($class)) {
                $class::clean_up();
            }
        }
        $this->mailboxes = [];
    }
}
