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
        $envDistFile = APP_PATH . '.env';
        if (!file_exists($envDistFile)) {
            Hm_Msgs::add('ERR.env file not found at: "' . $envDistFile . '"');
            return;
        }

        $envFile = static::get('CYPHT_DOTENV');
        $dotenvLoader->load($envDistFile);
        if ($envFile) {
            $dotenvLoader->loadEnv($envFile);
        }
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
            // Use require to include the file
            $fileArray = require $file;

            if(is_array($fileArray)) {
                // Check if values are boolean and convert if necessary
                $fileArray = array_map(function ($value) {
                    return is_array($value) ? $value : (
                        is_string($value) && strtolower($value) === 'true' ? true : (
                            is_string($value) && strtolower($value) === 'false' ? false : $value
                        )
                    );
                }, $fileArray);

                // Merge the arrays
                $configArray = array_merge($configArray, $fileArray);
            }
        }
        return $configArray;
    }
}

if (!function_exists('config')) {
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
    function config($file_name) {
        // Use require to include the file
        $fileArray = require CONFIG_PATH.$file_name.'.php';

        // Check if values are boolean and convert if necessary
        return array_map(function ($value) {
            return is_array($value) ? $value : (
                is_string($value) && strtolower($value) === 'true' ? true : (
                    is_string($value) && strtolower($value) === 'false' ? false : $value
                )
            );
        }, $fileArray);
    }
}
