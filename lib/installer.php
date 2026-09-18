<?php

/**
 * Orchestrates the install steps (env, directories, database, admin account,
 * config build) by driving the existing CLI scripts. Holds no business logic
 * of its own so the CLI wizard and, later, a web installer share one path.
 */
class Hm_Installer {

    private $php_binary;
    private $app_path;

    public function __construct($app_path = APP_PATH) {
        $this->php_binary = PHP_BINARY;
        $this->app_path = $app_path;
    }

    public function envExists() {
        return file_exists($this->app_path.'.env');
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
        $template = file_get_contents($this->app_path.'.env.example');
        foreach ($values as $key => $value) {
            $line = $key.'='.$this->escapeEnvValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            if (preg_match($pattern, $template)) {
                // preg_replace_callback, not preg_replace: a string replacement
                // would reinterpret backslashes and $ in $line as backreferences.
                $template = preg_replace_callback($pattern, fn() => $line, $template, 1);
            }
            else {
                $template .= "\n".$line;
            }
        }
        file_put_contents($this->app_path.'.env', $template);
    }

    /**
     * Always double-quotes and escapes backslash, double quote and dollar
     * sign, in that order, so Symfony Dotenv (which supports multi-line
     * quoted values and ${VAR} interpolation, like bash) treats the result
     * as one opaque literal, whatever the value contains.
     */
    private function escapeEnvValue($value) {
        $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
        return '"'.$escaped.'"';
    }

    public function createDirectories(array $dirs) {
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
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
        $parts = array_merge([$this->php_binary, $this->app_path.$relative_path], $args);
        $command = implode(' ', array_map('escapeshellarg', $parts));

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->app_path);
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
