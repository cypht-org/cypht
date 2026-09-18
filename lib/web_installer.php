<?php

/**
 * Security logic for the web installer, kept separate from install.php so it
 * can be unit tested without executing an HTTP request. install.php only
 * wires this to $_SERVER/$_POST/session and echoes the result.
 */
class Hm_Web_Installer {

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
        $this->installer->writeEnv($values);
        $this->installer->createDirectories([$values['USER_SETTINGS_DIR'], $values['ATTACHMENT_DIR']]);

        $db_result = $this->installer->setupDatabase();

        $admin_result = ['success' => true, 'output' => '', 'error' => ''];
        if (trim((string) $admin_user) !== '') {
            $admin_result = $this->installer->createAdminAccount(trim($admin_user), (string) $admin_pass);
        }

        $build_result = $this->installer->buildConfig();

        return [
            'success' => $db_result['success'] && $admin_result['success'] && $build_result['success'],
            'output' => $db_result['output'].$db_result['error']."\n".
                $admin_result['output'].$admin_result['error']."\n".
                $build_result['output'].$build_result['error'],
        ];
    }
}
