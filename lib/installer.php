<?php

/**
 * Orchestrates the install steps (env, directories, database, admin account,
 * config build) by driving the existing CLI scripts. Holds no business logic
 * of its own so the CLI wizard and, later, a web installer share one path.
 */
class Hm_Installer {

    private $php_binary;

    public function __construct() {
        $this->php_binary = PHP_BINARY;
    }

    public function envExists() {
        return file_exists(APP_PATH.'.env');
    }

    public function checkRequirements() {
        return [
            'php_version' => version_compare(PHP_VERSION, '8.1', '>='),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => function_exists('openssl_random_pseudo_bytes'),
            'dom' => extension_loaded('dom'),
            'pdo' => class_exists('PDO', false),
        ];
    }

    public function writeEnv(array $values) {
        $template = file_get_contents(APP_PATH.'.env.example');
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->escapeEnvValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $template = preg_match($pattern, $template)
                ? preg_replace($pattern, $line, $template, 1)
                : $template."\n".$line;
        }
        file_put_contents(APP_PATH.'.env', $template);
    }

    private function escapeEnvValue($value) {
        if (preg_match('/[\s#"]/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }
        return $value;
    }

    public function createDirectories(array $dirs) {
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new RuntimeException('Unable to create directory: '.$dir);
            }
        }
    }

    public function setupDatabase() {
        return $this->runScript('scripts/setup_database.php');
    }

    public function createAdminAccount($username, $password) {
        return $this->runScript('scripts/create_account.php', [$username, $password]);
    }

    public function buildConfig() {
        return $this->runScript('scripts/config_gen.php');
    }

    private function runScript($relative_path, array $args = []) {
        $parts = array_merge([$this->php_binary, APP_PATH.$relative_path], $args);
        $command = implode(' ', array_map('escapeshellarg', $parts));

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, APP_PATH);
        if (!is_resource($process)) {
            return ['success' => false, 'output' => '', 'error' => 'Unable to start '.$relative_path];
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['success' => proc_close($process) === 0, 'output' => $output, 'error' => $error];
    }
}
