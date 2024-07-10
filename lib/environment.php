<?php

/**
 * Environment objects
 * @package framework
 * @subpackage environment
 */

class Hm_Environment {

    private static $instance;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function load() {
        $this->set_required_environment_variables();

        $dotenvLoader = new \Symfony\Component\Dotenv\Dotenv();
        if (method_exists($dotenvLoader, 'usePutenv')) {
            $dotenvLoader->usePutenv(true);
        }
        $envFile = static::get('CYPHT_DOTENV');
        if (!file_exists($envFile)) {
            Hm_Msgs::add('ERR.env file not found at: "' . $envFile . '"');
            return;
        }
        $dotenvLoader->load($envFile);
    }

    public static function get($key, $default = null) {
        $variables = self::getInstance()->get_environment_variables();
        return $variables[$key] ?? $default;
    }

    /**
     * Sets required environment variables that are used within .env files
     */
    private function set_required_environment_variables() {
        $_ENV['CYPHT_DOTENV'] = APP_PATH . '.env';
    }

    /**
     * Get a merge of environment variables $_ENV and $_SERVER.
     *
     * @return array
     */
    protected function get_environment_variables() {
        return array_merge($_ENV, $_SERVER);
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function env($key, $default = null) {
        return getenv($key) ?: $default;
    }
}

if (!function_exists('merge_config_files')) {
    /**
     * Merge configuration arrays from PHP files in the specified folder.
     *
     * This function includes each PHP file in the specified folder and retrieves its array.
     * It then merges these arrays into a single configuration array, applying boolean conversion
     * for values that are represented as "true" or "false" strings.
     *
     * @param string $folder_path The path to the folder containing PHP configuration files.
     *
     * @return array The merged configuration array.
     */
    function merge_config_files($folder_path) {
        $configArray = [];

        // Get all PHP files in the specified folder
        $files = glob($folder_path . '/*.php');

        foreach ($files as $file) {
            $fileArray = process_config_array($file);
            $configArray = array_merge($configArray, $fileArray);
        }
        return $configArray;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration data for a single file
     *
     * @param string $file_name The path to PHP configuration file.
     *
     * @return array The configuration array.
     */
    function config($file_name) {
        $path = CONFIG_PATH.$file_name.'.php';
        return process_config_array($path);
    }
}

if (!function_exists('process_config_array')) {
    function process_config_array($filename) {
        $array = require $filename;
        if (is_array($array)) {
            return array_map(function ($value) {
                return is_array($value) ? $value : (
                    is_string($value) && mb_strtolower($value) === 'true' ? true : (
                        is_string($value) && mb_strtolower($value) === 'false' ? false : $value
                    )
                );
            }, $array);
        }
        return [];
    }
}
