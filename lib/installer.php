<?php

/**
 * Orchestrates the install steps (env, directories, database, admin account,
 * config build) by calling the same lib functions the CLI scripts use, in
 * the current process. Not a subprocess: PHP_BINARY is unreliable outside
 * the CLI SAPI (under Apache's module SAPI it resolves to httpd itself, not
 * php), and many shared hosts disable proc_open/exec entirely.
 */
class Hm_Installer {

    private $app_path;
    private $bootstrapped = false;

    public function __construct($app_path = APP_PATH) {
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

    /**
     * A single, fast connection attempt with the values as submitted, before
     * .env is written or any directory is created. Lets a wrong password or
     * unreachable host fail immediately instead of after the 10-retry loop
     * in run_database_setup(), and without leaving a half-written .env.
     */
    public function testDatabaseConnection(array $values) {
        $driver = $values['DB_DRIVER'] ?? '';
        $dsn = $driver === 'sqlite'
            ? "sqlite:{$values['DB_NAME']}"
            : "{$driver}:host={$values['DB_HOST']};dbname={$values['DB_NAME']}";
        try {
            new PDO($dsn, $values['DB_USER'] ?? '', $values['DB_PASS'] ?? '');
            return ['success' => true, 'error' => ''];
        }
        catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function setupDatabase() {
        return $this->run(function () {
            $config = $this->siteConfig();
            run_database_setup($config, $this->app_path.'database/migrations');
        });
    }

    public function createAdminAccount($username, $password) {
        return $this->run(function () use ($username, $password) {
            $result = create_user_account($username, $password, $this->siteConfig());
            echo $result['message']."\n";
        });
    }

    public function buildConfig() {
        return $this->run(function () {
            // Several build steps (combine_includes, create_production_site, ...)
            // use paths relative to the app directory, like the CLI script does.
            $previous_cwd = getcwd();
            chdir($this->app_path);
            try {
                build_config();
            }
            finally {
                chdir($previous_cwd);
            }
        });
    }

    private function siteConfig() {
        $config = new Hm_Site_Config_File();
        Hm_Environment::getInstance()->define_default_constants($config);
        return $config;
    }

    private function bootstrap() {
        if ($this->bootstrapped) {
            return;
        }
        require_once $this->app_path.'lib/define_vendor_path.php';
        require_once VENDOR_PATH.'autoload.php';
        require_once $this->app_path.'lib/framework.php';
        require_once $this->app_path.'lib/config_builder.php';
        require_once $this->app_path.'lib/database_setup.php';
        require_once $this->app_path.'lib/account_manager.php';
        Hm_Environment::getInstance()->load();
        if (!defined('DEBUG_MODE')) {
            define('DEBUG_MODE', filter_var(env('ENABLE_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
        }
        $this->bootstrapped = true;
    }

    private function run(callable $fn) {
        $this->bootstrap();
        ob_start();
        try {
            $fn();
            return ['success' => true, 'output' => ob_get_clean(), 'error' => ''];
        }
        catch (Throwable $e) {
            return ['success' => false, 'output' => ob_get_clean(), 'error' => $e->getMessage()];
        }
    }
}
