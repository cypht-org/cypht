<?php

/**
 * To use these overrides, you must first enable the "site" module in your
 * config/app.php file to activate the module.
 */

/**
 * Override the session class. These are the methods that must be overriden to
 * create a new session backend. The "session_type" value in your config/app.php must
 * be set to "custom" to activate this class. There are several other
 * properties and methods that can be modified to create custom sessions:
 *
 *  https://cypht.org/docs/code_docs/class-Hm_Session.html
 *
 * This example extends the standard PHP session class. You can also extend the
 * DB or Memcached classes, or the base session class. In this example we just
 * defer to the PHP session class methods.
 *
 * @package modules
 * @subpackage site
 */
class Custom_Session extends Hm_PHP_Session {

    use Hm_Session_Auth;
    
    private $existing = false;
    private $sessionPrefix;
    private $sessionDir;
    private $sessionTtl;
    private $gcDivisor;
    private $debug;
    
    public function __construct() {
        parent::__construct();
        
        // ALL from .env - no hardcoding
        $this->sessionPrefix = getenv('CYPHT_SESSION_PREFIX') ?: 'cypht_';
        $this->sessionDir = getenv('CYPHT_SESSION_DIR') ?: null;
        $this->sessionTtl = (int) (getenv('CYPHT_SESSION_TTL') ?: 604800);
        $this->gcDivisor = (int) (getenv('CYPHT_SESSION_GC_DIVISOR') ?: 200);
        $this->debug = getenv('CYPHT_SESSION_DEBUG') === 'true';
        
        $this->cname = $this->sessionPrefix . 'session';
    }

    private function session_dir() {
        if ($this->sessionDir) {
            $base = rtrim($this->sessionDir, '/\\');
        } else {
            $base = dirname(rtrim(Hm_Environment::get('USER_SETTINGS_DIR', sys_get_temp_dir()), '/\\'));
        }
        
        $integrationType = getenv('CYPHT_INTEGRATION_TYPE') ?: 'standalone';
        $subdir = $integrationType !== 'standalone' ? $integrationType . '_sessions' : 'sessions';
        $fullDir = $base . '/' . $subdir;
        
        if (!is_dir($fullDir)) {
            @mkdir($fullDir, 0700, true);
        }
        return $fullDir;
    }

    private function session_file($key) {
        return $this->session_dir() . '/' . preg_replace('/[^a-f0-9]/', '', (string) $key) . '.session';
    }

    private function dbg($line) {
        if ($this->debug !== true) {
            return;
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '?';
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '?';
        $logPath = dirname(rtrim(APP_PATH, '/\\'), 3) . '/session_debug.log';
        @file_put_contents(
            $logPath,
            sprintf("[%s] pid=%d %s %s key=%s :: %s\n", 
                date('H:i:s.u'), getmypid(), $method, $uri, 
                substr((string) $this->session_key, 0, 8), $line),
            FILE_APPEND
        );
    }

    private function gc() {
        $divisor = $this->gcDivisor;
        if ($divisor < 1 || random_int(1, $divisor) !== 1) {
            return;
        }
        $ttl = $this->sessionTtl;
        if ($ttl < 3600) {
            $ttl = 3600;
        }
        $cutoff = time() - $ttl;
        $files = @glob($this->session_dir() . '/*.session');
        if (!is_array($files)) {
            return;
        }
        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function write_locked() {
        $fh = @fopen($this->session_file($this->session_key), 'cb');
        if ($fh !== false) {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $this->ciphertext($this->data));
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * check for an active session or an attempt to start one
     * @param object $request request object
     * @return bool
     */
    public function check($request, $user=false, $pass=false, $fingerprint=true) {
        if ($user !== false && $pass !== false) {
            if ($this->auth($user, $pass)) {
                $this->set_key($request);
                $this->session_key = bin2hex(random_bytes(16));
                $this->loaded = true;
                $this->data = [];
                $this->active = true;
                $this->dbg('NEW LOGIN established');
                $this->gc();
                if ($fingerprint) {
                    $this->set_fingerprint($request);
                } else {
                    $this->set('fingerprint', '');
                }
                $this->save_auth_detail();
                $this->just_started();
                return true;
            }
        } elseif (array_key_exists($this->cname, $request->cookie)) {
            $this->session_key = $request->cookie[$this->cname];
            $this->dbg('checking existing session cookie');
            $this->get_key($request);
            $this->existing = true;
            $this->start($request, true);
            if ($this->active) {
                $this->check_fingerprint($request);
                $this->dbg('active after fingerprint check = ' . ($this->active ? 'yes' : 'no'));
            }
        } else {
            $this->dbg('no cookie, no user/pass; anonymous request');
        }
        return $this->is_active();
        // return parent::check($request, $user, $pass, $fingerprint);
    }

    /**
     * Start the session. This could be an existing session or a new login
     * @param object $request request details
     * @return void
     */
    public function start($request, $existing_session=false) {
        // return parent::start($request, $existing_session);
             if (!$existing_session) {
            return;
        }
        $file = $this->session_file($this->session_key);
        if (!is_readable($file)) {
            $this->active = false;
            return;
        }
        $fh = @fopen($file, 'rb');
        if ($fh === false) {
            $this->active = false;
            return;
        }
        flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        $data = $this->plaintext($raw);
        if (is_array($data)) {
            $this->data = $data;
            $this->active = true;
        } else {
            $this->active = false;
        }
    }

    /**
     * Call the configured authentication method to check user credentials
     * @param string $user username
     * @param string $pass password
     * @return bool true if the authentication was successful
     */
    public function auth($user, $pass) {
        return parent::auth($user, $pass);
    }

    /**
     * Return a session value, or a user settings value stored in the session
     * @param string $name session value name to return
     * @param mixed $default value to return if $name is not found
     * @return mixed the value if found, otherwise $defaultHm_Auth
     */
    public function get($name, $default=false, $user=false) {
        // return parent::get($name, $default, $user);
        if ($user) {
            return (array_key_exists('user_data', $this->data) && 
                    array_key_exists($name, $this->data['user_data'])) 
                ? $this->data['user_data'][$name] : $default;
        }
        return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
    }

    /**
     * Save a value in the session
     * @param string $name the name to save
     * @param string $value the value to save
     * @return void
     */
    public function set($name, $value, $user=false) {
        // return parent::set($name, $value);
        if ($user) {
            $this->data['user_data'][$name] = $value;
        } else {
            $this->data[$name] = $value;
        }
    }

    /**
     * Delete a value from the session
     * @param string $name name of value to deleteHm_Auth
     * @return void
     */
    public function del($name) {
        // return parent::del($name);
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
            return true;
        }
        return false;
    }

    /**
     * End a session after a page request is complete. This only closes the session and
     * does not destroy it
     * @return void
     */
    public function end() {
        // return parent::end();
        if ($this->active && !$this->session_closed) {
            $this->write_locked();
        }
        $this->active = false;
    }

    /**
     * Destroy a session for good
     * @param object $request request details
     * @return void
     */
    public function destroy($request) {
        // return parent::destroy($request);
        @unlink($this->session_file($this->session_key));
        $this->delete_cookie($request, $this->cname);
        $this->delete_cookie($request, 'hm_id');
        $this->active = false;
    }

}

/**
 * Override the authentication class. This method needs to be overriden to
 * create a custom authentication backend. You must set the "auth_type" setting
 * in your config/app.php file to "custom" to activate this class. More information
 * about the base class for authentication is located here:
 *
 * https://cypht.org/docs/code_docs/class-Hm_Auth.html
 *
 * This example extends the auth DB class, and simply defers the parent class
 * method
 * @package modules
 * @subpackage site
 */
class Custom_Auth extends Hm_Auth_DB {

    private $ssoSecret;
    private $ssoTimeout;
    private $ssoAlgorithm;
    
    public function __construct() {
        parent::__construct();

        $this->ssoSecret = getenv('CYPHT_SSO_SECRET') ?: '';
        $this->ssoTimeout = (int) (getenv('CYPHT_SSO_TIMEOUT') ?: 60);
        $this->ssoAlgorithm = getenv('CYPHT_SSO_ALGORITHM') ?: 'sha256';
    }

    /**
     * This is the method new auth mechs need to override.
     * @param string $user username
     * @param string $pass password
     * @return bool true if the user is authenticated, false otherwise
     */
    public function check_credentials($user, $pass) {
        $authType = getenv('CYPHT_AUTH_TYPE') ?: 'db';
        
        if ($authType === 'sso') {
            return $this->check_sso($user, $pass);
        }
    
        return parent::check_credentials($user, $pass);
    }

    private function check_sso($user, $pass) {
        if ($this->ssoSecret === '' || strpos($pass, '.') === false) {
            return false;
        }
        
        list($timestamp, $signature) = explode('.', $pass, 2);
        if (!ctype_digit($timestamp)) {
            return false;
        }
        
        if (abs(time() - (int) $timestamp) > $this->ssoTimeout) {
            return false;
        }
        
        $expected = hash_hmac($this->ssoAlgorithm, $user . '|' . $timestamp, $this->ssoSecret);
        return hash_equals($expected, $signature);
    }
}

class Custom_User_Config extends Hm_Config {
    
    private $site_config;
    private $username;
    private $dirty = false;
    private $flush_registered = false;
    private $dbh = null;
    
    // ALL from .env - no hardcoding
    private $encryptionSecret;
    private $configType;
    private $dbPrefix;
    private $configTable;
    private $userTable;
    private $idField;
    private $loginField;
    private $entityField;
    
    const PASS_PREFIX = 'enc:v1:';
    
    public function __construct($config) {
        $this->site_config = $config;
        $this->config = array_merge($this->config, $config->user_defaults);

        $this->encryptionSecret = getenv('CYPHT_CONFIG_ENCRYPTION_SECRET') ?: '';
        $this->configType = getenv('CYPHT_USER_CONFIG_TYPE') ?: 'file';
        $this->dbPrefix = getenv('CYPHT_DB_PREFIX') ?: '';
        $this->configTable = getenv('CYPHT_CONFIG_TABLE') ?: 'user_config';
        $this->userTable = getenv('CYPHT_USER_TABLE') ?: 'users';
        $this->idField = getenv('CYPHT_USER_ID_FIELD') ?: 'id';
        $this->loginField = getenv('CYPHT_USER_LOGIN_FIELD') ?: 'username';
        $this->entityField = getenv('CYPHT_USER_ENTITY_FIELD') ?: '';
    }
    
    private function get_path($username) {
        $dir = rtrim((string) $this->site_config->get('user_settings_dir', false), '/\\');
        $safe = preg_replace('/[^a-zA-Z0-9_.@-]/', '_', (string) $username);
        $safe = substr($safe, 0, 64);
        $fingerprint = substr(hash('sha256', (string) $username), 0, 12);
        return $dir . '/' . $safe . '-' . $fingerprint . '.json';
    }
    
    private function db() {
        if ($this->dbh === null) {
            $this->dbh = Hm_DB::connect($this->site_config);
        }
        return $this->dbh;
    }
    
    private function resolve_user($username) {
        $dbh = $this->db();
        if (!$dbh) {
            return false;
        }
        
        $fields = array($this->idField . ' as id');
        if ($this->entityField) {
            $fields[] = $this->entityField . ' as entity';
        }
        
        $query = 'select ' . implode(', ', $fields) . 
                 ' from ' . $this->dbPrefix . $this->userTable . 
                 ' where ' . $this->loginField . ' = ?';
        
        $row = Hm_DB::execute($dbh, $query, array($username), 'select');
        
        if (!is_array($row) || empty($row['id'])) {
            return false;
        }
        
        return array(
            'id' => (int) $row['id'],
            'entity' => (int) (empty($row['entity']) ? 1 : $row['entity'])
        );
    }
    
    private function db_load($username) {
        $user = $this->resolve_user($username);
        if ($user === false) {
            return false;
        }
        
        $configTable = $this->dbPrefix . $this->configTable;
        $where = 'fk_user = ?';
        $params = array($user['id']);
        
        if ($this->entityField) {
            $where .= ' and entity = ?';
            $params[] = $user['entity'];
        }
        
        $row = Hm_DB::execute(
            $this->db(),
            'select config from ' . $configTable . ' where ' . $where,
            $params,
            'select'
        );
        
        if (!is_array($row) || !isset($row['config']) || $row['config'] === '') {
            return false;
        }
        
        return $row['config'];
    }

    private function file_load($username) {
        $source = $this->get_path($username);
        if (!is_readable($source)) {
            return false;
        }
        $raw = file_get_contents($source);
        if ($raw === false || $raw === '') {
            return false;
        }
        return $raw;
    }

    private function encrypt_passwords($config) {
        if ($this->encryptionSecret === '') {
            return $config;
        }
        foreach (array('imap_servers', 'smtp_servers', 'pop3_servers') as $key) {
            if (empty($config[$key]) || !is_array($config[$key])) {
                continue;
            }
            foreach ($config[$key] as $index => $server) {
                if (!is_array($server) || empty($server['pass'])) {
                    continue;
                }
                if (strpos($server['pass'], self::PASS_PREFIX) === 0) {
                    continue;
                }
                $config[$key][$index]['pass'] = self::PASS_PREFIX . 
                    Hm_Crypt::ciphertext($server['pass'], $this->encryptionSecret);
            }
        }
        return $config;
    }

    private function decrypt_passwords($config) {
        if ($this->encryptionSecret === '') {
            return $config;
        }
        foreach (array('imap_servers', 'smtp_servers', 'pop3_servers') as $key) {
            if (empty($config[$key]) || !is_array($config[$key])) {
                continue;
            }
            foreach ($config[$key] as $index => $server) {
                if (!is_array($server) || empty($server['pass'])) {
                    continue;
                }
                if (strpos($server['pass'], self::PASS_PREFIX) !== 0) {
                    continue;
                }
                $plain = Hm_Crypt::plaintext(substr($server['pass'], strlen(self::PASS_PREFIX)), $this->encryptionSecret);
                $config[$key][$index]['pass'] = ($plain === false ? '' : $plain);
            }
        }
        return $config;
    }

    public function load($username, $key = null) {
        $this->username = $username;

        if ($this->configType === 'db') {
            $str_data = $this->db_load($username);
        } else {
            $str_data = $this->file_load($username);
        }

        if ($str_data === false || $str_data === '') {
            return;
        }

        $data = $this->decode($str_data);
        if (is_array($data)) {
            $this->config = array_merge($this->config, $this->decrypt_passwords($data));
            $this->set_tz();
        }
    }

    public function reload($data, $username = false) {
        $this->username = $username;
        $this->config = $data;
        $this->set_tz();
        if (!$username) {
            return;
        }
        $this->dirty = true;
        if (!$this->flush_registered) {
            $this->flush_registered = true;
            register_shutdown_function(array($this, 'flush_pending'));
        }
    }

    public function flush_pending() {
        if (!$this->dirty || !$this->username) {
            return;
        }
        $this->dirty = false;
        ob_start();
        try {
            if ($this->configType === 'db') {
                $raw = $this->db_load($this->username);
            } else {
                $raw = $this->file_load($this->username);
            }
            $existing = ($raw === false || $raw === '') ? array() : $this->decode($raw);
            if (!is_array($existing)) {
                $existing = array();
            }
            $existing = $this->decrypt_passwords($existing);
            if ($this->comparable($existing) === $this->comparable($this->config)) {
                ob_end_clean();
                return;
            }
            if (!empty($existing['updated_at']) && !empty($this->config['updated_at']) &&
                $existing['updated_at'] > $this->config['updated_at']) {
                ob_end_clean();
                return;
            }
            $this->save($this->username);
        } catch (Exception $e) {
            Hm_Debug::add('User config: deferred save failed: ' . $e->getMessage());
        } catch (Throwable $e) {
            Hm_Debug::add('User config: deferred save failed: ' . $e->getMessage());
        }
        ob_end_clean();
    }

    private function comparable($config) {
        unset($config['updated_at']);
        foreach (array('pop3_servers', 'imap_servers', 'smtp_servers') as $key) {
            if (empty($config[$key]) || !is_array($config[$key])) {
                continue;
            }
            foreach ($config[$key] as $index => $server) {
                if (is_array($server)) {
                    unset($config[$key][$index]['object'], $config[$key][$index]['connected']);
                }
            }
        }
        ksort($config);
        return json_encode($config);
    }

    public function set($name, $value) {
        $this->config[$name] = $value;
        if (!$this->username) {
            return;
        }
        $this->dirty = true;
        if (!$this->flush_registered) {
            $this->flush_registered = true;
            register_shutdown_function(array($this, 'flush_pending'));
        }
    }

    public function save($username, $key = null) {
        $this->dirty = false;
        $this->shuffle();
        $removed = $this->filter_servers();
        $this->config['updated_at'] = microtime(true);
        ksort($this->config);
        $payload = json_encode($this->encrypt_passwords($this->config));

        if ($this->configType === 'db') {
            $this->db_save($username, $payload);
        } else {
            $this->file_save($username, $payload);
        }

        $this->restore_servers($removed);
    }

    private function db_save($username, $payload) {
        $user = $this->resolve_user($username);
        if ($user === false) {
            return false;
        }

        $dbh = $this->db();
        if (!$dbh) {
            return false;
        }

        $configTable = $this->dbPrefix . $this->configTable;
        $where = 'fk_user = ?';
        $params = array($user['id']);
        
        if ($this->entityField) {
            $where .= ' and entity = ?';
            $params[] = $user['entity'];
        }

        $updated = Hm_DB::execute(
            $dbh,
            'update ' . $configTable . ' set config = ? where ' . $where,
            array_merge(array($payload), $params),
            'modify'
        );

        if ($updated === false) {
            return false;
        }
        if ((int) $updated > 0) {
            return true;
        }

        if ($this->entityField) {
            $inserted = Hm_DB::execute(
                $dbh,
                'insert into ' . $configTable . ' (entity, fk_user, config, date_creation) values (?, ?, ?, ?)',
                array($user['entity'], $user['id'], $payload, date('Y-m-d H:i:s')),
                'insert'
            );
        } else {
            $inserted = Hm_DB::execute(
                $dbh,
                'insert into ' . $configTable . ' (fk_user, config, date_creation) values (?, ?, ?)',
                array($user['id'], $payload, date('Y-m-d H:i:s')),
                'insert'
            );
        }
        
        return $inserted !== false;
    }

    private function file_save($username, $payload) {
        $path = $this->get_path($username);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return @file_put_contents($path, $payload) !== false;
    }

    public function filter_servers() {
        foreach ($this->config as $key => $vals) {
            if (in_array($key, array('pop3_servers', 'imap_servers', 'smtp_servers'), true) && is_array($vals)) {
                foreach ($vals as $index => $server) {
                    if (is_array($server)) {
                        $this->config[$key][$index]['object'] = false;
                        $this->config[$key][$index]['connected'] = false;
                    }
                }
            }
        }
        return parent::filter_servers();
    }
}

/*
function format_msg_html($str, $images=false) {
    return '';
}
*/
