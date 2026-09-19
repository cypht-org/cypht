<?php

/**
 * Security logic for the web installer, kept separate from install.php so it
 * can be unit tested without executing an HTTP request. install.php only
 * wires this to $_SERVER/$_POST/session and echoes the result.
 */
class Hm_Web_Installer {

    private const ALLOWED_DB_DRIVERS = ['mysql', 'pgsql', 'sqlite'];

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
     */
    public static function validate(array $values, $app_path) {
        $errors = [];
        if (!in_array($values['DB_DRIVER'], self::ALLOWED_DB_DRIVERS, true)) {
            $errors[] = 'Database driver must be one of: '.implode(', ', self::ALLOWED_DB_DRIVERS);
        }
        foreach (['USER_SETTINGS_DIR' => 'User settings directory', 'ATTACHMENT_DIR' => 'Attachment directory'] as $key => $label) {
            if ($values[$key] === '') {
                $errors[] = $label.' is required';
            }
            elseif (self::isUnderPath($values[$key], $app_path)) {
                $errors[] = $label.' must be outside the web root ('.$values[$key].' is inside it)';
            }
        }
        return $errors;
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
        $fields = [
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'cypht_db',
            'DB_USER' => 'cypht',
            'DB_PASS' => '',
            'USER_SETTINGS_DIR' => '/var/lib/hm3/users',
            'ATTACHMENT_DIR' => '/var/lib/hm3/attachments',
        ];
        $values = [];
        foreach ($fields as $key => $default) {
            $values[$key] = trim((string) ($post[$key] ?? $default));
        }
        $values['AUTH_TYPE'] = 'DB';
        $values['USER_CONFIG_TYPE'] = 'file';
        return $values;
    }

    public function install(array $values, $admin_user, $admin_pass) {
        $connection_check = $this->installer->testDatabaseConnection($values);
        if (!$connection_check['success']) {
            return ['success' => false, 'env_written' => false,
                'output' => 'Could not connect to the database: '.$connection_check['error']];
        }

        $this->installer->writeEnv($values);
        $this->installer->createDirectories([$values['USER_SETTINGS_DIR'], $values['ATTACHMENT_DIR']]);

        $output = '';

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

        $build_result = $this->installer->buildConfig();
        $output .= "\n".$build_result['output'].$build_result['error'];

        return ['success' => $build_result['success'], 'env_written' => true, 'output' => $output];
    }
}
