<?php

/**
 * Security logic for the web installer, kept separate from install.php so it
 * can be unit tested without executing an HTTP request. install.php only
 * wires this to $_SERVER/$_POST/session and echoes the result.
 */
class Hm_Web_Installer {

    private const ALLOWED_DB_DRIVERS = ['mysql', 'pgsql', 'sqlite'];
    private const ALLOWED_AUTH_TYPES = ['DB', 'IMAP', 'LDAP'];

    private const FIELD_GROUPS = [
        'DB' => ['DB_DRIVER' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_NAME' => 'cypht_db',
            'DB_USER' => 'cypht', 'DB_PASS' => ''],
        'IMAP' => ['IMAP_AUTH_SERVER' => 'localhost', 'IMAP_AUTH_PORT' => '143',
            'IMAP_AUTH_NAME' => 'localhost', 'IMAP_AUTH_SIEVE_CONF_HOST' => ''],
        'LDAP' => ['LDAP_AUTH_SERVER' => 'localhost', 'LDAP_AUTH_PORT' => '389',
            'LDAP_AUTH_BASE_DN' => 'dc=example,dc=com', 'LDAP_AUTH_UID_ATTR' => 'uid'],
    ];
    private const TLS_FIELDS = ['IMAP' => 'IMAP_AUTH_TLS', 'LDAP' => 'LDAP_AUTH_TLS'];

    private $installer;
    private $token_file;

    public function __construct(Hm_Installer $installer, $token_file) {
        $this->installer = $installer;
        $this->token_file = $token_file;
    }

    public function tokenExists() {
        return file_exists($this->token_file);
    }

    public function generateToken() {
        $token = bin2hex(random_bytes(24));
        file_put_contents($this->token_file, $token);
        @chmod($this->token_file, 0600);
        return $token;
    }

    public function tokenMatches($given) {
        if (!$this->tokenExists()) {
            return false;
        }
        return self::secretMatches(trim(file_get_contents($this->token_file)), $given);
    }

    public function consumeToken() {
        @unlink($this->token_file);
    }

    public static function secretMatches($expected, $given) {
        return is_string($expected) && $expected !== ''
            && is_string($given) && $given !== ''
            && hash_equals($expected, $given);
    }

    public static function isHttps(array $server) {
        return (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off')
            || (($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /**
     * Server-side validation, since a form's client-side constraints (a
     * restricted <select>, required attributes) can always be bypassed by
     * whoever is submitting directly. Keeping the settings/attachment
     * directories out of the web root is the exact protection issue #17's
     * security discussion asked for, not just a UX nicety.
     *
     * $only lets a single wizard step validate just its own fields.
     */
    public static function validate(array $values, $app_path, array $only = ['AUTH', 'DIRS']) {
        $errors = [];
        if (in_array('AUTH', $only, true)) {
            $errors = array_merge($errors, self::validateAuth($values));
        }
        if (in_array('DIRS', $only, true)) {
            foreach (['USER_SETTINGS_DIR' => 'User settings directory', 'ATTACHMENT_DIR' => 'Attachment directory'] as $key => $label) {
                if ($values[$key] === '') {
                    $errors[] = $label.' is required';
                }
                elseif (self::isUnderPath($values[$key], $app_path)) {
                    $errors[] = $label.' must be outside the web root ('.$values[$key].' is inside it)';
                }
            }
        }
        return $errors;
    }

    private static function validateAuth(array $values) {
        switch ($values['AUTH_TYPE'] ?? '') {
            case 'DB':
                if (!in_array($values['DB_DRIVER'], self::ALLOWED_DB_DRIVERS, true)) {
                    return ['Database driver must be one of: '.implode(', ', self::ALLOWED_DB_DRIVERS)];
                }
                return [];

            case 'IMAP':
                $errors = [];
                if ($values['IMAP_AUTH_SERVER'] === '') {
                    $errors[] = 'IMAP server is required';
                }
                if (!self::isValidPort($values['IMAP_AUTH_PORT'])) {
                    $errors[] = 'IMAP port must be a valid port number';
                }
                return $errors;

            case 'LDAP':
                $errors = [];
                if ($values['LDAP_AUTH_SERVER'] === '') {
                    $errors[] = 'LDAP server is required';
                }
                if (!self::isValidPort($values['LDAP_AUTH_PORT'])) {
                    $errors[] = 'LDAP port must be a valid port number';
                }
                if ($values['LDAP_AUTH_BASE_DN'] === '') {
                    $errors[] = 'LDAP base DN is required';
                }
                if ($values['LDAP_AUTH_UID_ATTR'] === '') {
                    $errors[] = 'LDAP UID attribute is required';
                }
                return $errors;

            default:
                return ['Auth type must be one of: '.implode(', ', self::ALLOWED_AUTH_TYPES)];
        }
    }

    private static function isValidPort($value) {
        return ctype_digit((string) $value) && (int) $value > 0 && (int) $value <= 65535;
    }

    // Walks up to the nearest existing ancestor (mkdir() elsewhere is
    // recursive) and checks that one is writable, without creating anything.
    public static function checkStorageWritable(array $values) {
        foreach (['USER_SETTINGS_DIR' => 'User settings directory', 'ATTACHMENT_DIR' => 'Attachment directory'] as $key => $label) {
            $dir = rtrim(str_replace('\\', '/', $values[$key]), '/');
            $probe = $dir;
            while ($probe !== '' && $probe !== '.' && !is_dir($probe)) {
                $parent = dirname($probe);
                if ($parent === $probe) {
                    break;
                }
                $probe = $parent;
            }
            if ($probe === '' || $probe === '.' || !is_dir($probe)) {
                return ['success' => false, 'error' => $label.': no existing parent directory found for '.$dir];
            }
            if (!is_writable($probe)) {
                return ['success' => false, 'error' => $label.' is not writable: '.$probe];
            }
        }
        return ['success' => true, 'error' => ''];
    }

    public static function isUnderPath($candidate, $base) {
        $normalize = fn($path) => rtrim(str_replace('\\', '/', $path), '/');
        $candidate = $normalize($candidate);
        $base = $normalize(realpath($base) ?: $base);
        if ($candidate === '' || $base === '') {
            return false;
        }
        return $candidate === $base || str_starts_with($candidate.'/', $base.'/');
    }

    public static function collectFormValues(array $post) {
        $auth_type = strtoupper(trim((string) ($post['AUTH_TYPE'] ?? 'DB')));
        if (!in_array($auth_type, self::ALLOWED_AUTH_TYPES, true)) {
            $auth_type = 'DB';
        }

        $values = [
            'USER_SETTINGS_DIR' => trim((string) ($post['USER_SETTINGS_DIR'] ?? '/var/lib/hm3/users')),
            'ATTACHMENT_DIR' => trim((string) ($post['ATTACHMENT_DIR'] ?? '/var/lib/hm3/attachments')),
            'AUTH_TYPE' => $auth_type,
            'USER_CONFIG_TYPE' => 'file',
        ];

        foreach (self::FIELD_GROUPS as $group => $fields) {
            foreach ($fields as $key => $default) {
                $values[$key] = $group === $auth_type ? trim((string) ($post[$key] ?? $default)) : $default;
            }
        }
        foreach (self::TLS_FIELDS as $group => $key) {
            $values[$key] = $group === $auth_type && !empty($post[$key]) ? 'true' : 'false';
        }

        // IMAP_AUTH_NAME is just a display label; default it to the server.
        if ($auth_type === 'IMAP' && $values['IMAP_AUTH_NAME'] === '') {
            $values['IMAP_AUTH_NAME'] = $values['IMAP_AUTH_SERVER'] !== '' ? $values['IMAP_AUTH_SERVER'] : 'localhost';
        }

        return $values;
    }

    // DB gets a real PDO connection attempt; IMAP/LDAP only get a TCP
    // reachability check, since a full handshake needs credentials the
    // wizard doesn't collect.
    public function testAuthConnection(array $values) {
        switch ($values['AUTH_TYPE'] ?? '') {
            case 'DB':
                return $this->installer->testDatabaseConnection($values);
            case 'IMAP':
                return self::checkTcpReachable($values['IMAP_AUTH_SERVER'], $values['IMAP_AUTH_PORT']);
            case 'LDAP':
                return self::checkTcpReachable($values['LDAP_AUTH_SERVER'], $values['LDAP_AUTH_PORT']);
            default:
                return ['success' => false, 'error' => 'Unknown auth type.'];
        }
    }

    private static function checkTcpReachable($host, $port, $timeout = 5) {
        $errno = 0;
        $errstr = '';
        $conn = @fsockopen($host, (int) $port, $errno, $errstr, $timeout);
        if ($conn) {
            fclose($conn);
            return ['success' => true, 'error' => ''];
        }
        return ['success' => false, 'error' => $errstr !== '' ? $errstr : 'Could not reach '.$host.':'.$port];
    }

    public function install(array $values, $admin_user, $admin_pass) {
        $connection_check = $this->testAuthConnection($values);
        if (!$connection_check['success']) {
            return ['success' => false, 'env_written' => false,
                'output' => 'Could not verify the '.$values['AUTH_TYPE'].' auth settings: '.$connection_check['error']];
        }

        $this->installer->writeEnv($values);
        $this->installer->createDirectories([$values['USER_SETTINGS_DIR'], $values['ATTACHMENT_DIR']]);

        $output = '';

        if ($values['AUTH_TYPE'] === 'DB') {
            $db_result = $this->installer->setupDatabase();
            $output .= $db_result['output'].$db_result['error'];
            if (!$db_result['success']) {
                return ['success' => false, 'env_written' => true, 'output' => $output];
            }

            if (trim((string) $admin_user) !== '') {
                $admin_result = $this->installer->createAdminAccount(trim($admin_user), (string) $admin_pass);
                $output .= "\n".$admin_result['output'].$admin_result['error'];
                if (!$admin_result['success']) {
                    return ['success' => false, 'env_written' => true, 'output' => $output];
                }
            }
        }

        $build_result = $this->installer->buildConfig();
        $output .= "\n".$build_result['output'].$build_result['error'];

        return ['success' => $build_result['success'], 'env_written' => true, 'output' => $output];
    }
}
