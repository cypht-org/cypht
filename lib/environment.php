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

    public function load($file = '.env') {
        $this->set_required_environment_variables($file);

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

    public function define_default_constants($config) {
        define('DEFAULT_SEARCH_SINCE', $config->get('default_setting_search_since', '-1 week'));
        define('DEFAULT_UNREAD_SINCE', $config->get('default_setting_unread_since', '-1 week'));
        define('DEFAULT_UNREAD_PER_SOURCE', $config->get('default_setting_unread_per_source', 20));
        define('DEFAULT_FLAGGED_SINCE', $config->get('default_setting_flagged_since', '-1 week'));
        define('DEFAULT_FLAGGED_PER_SOURCE', $config->get('default_setting_flagged_per_source', 20));
        define('DEFAULT_ALL_SINCE', $config->get('default_setting_all_since', '-1 week'));
        define('DEFAULT_ALL_PER_SOURCE', $config->get('default_setting_all_per_source', 20));
        define('DEFAULT_ALL_EMAIL_SINCE', $config->get('default_setting_all_email_since', '-1 week'));
        define('DEFAULT_ALL_EMAIL_PER_SOURCE', $config->get('default_setting_all_email_per_source', 20));
        define('DEFAULT_FEED_SINCE', $config->get('default_setting_feed_since', '-1 week'));
        define('DEFAULT_FEED_LIMIT', $config->get('default_setting_feed_limit', 20));
        define('DEFAULT_SENT_SINCE', $config->get('default_setting_sent_since', '-1 week'));
        define('DEFAULT_SENT_PER_SOURCE', $config->get('default_setting_sent_per_source', 20));
        define('DEFAULT_UNREAD_EXCLUDE_FEEDS', $config->get('default_setting_unread_exclude_feeds', false));
        define('DEFAULT_LIST_STYLE', $config->get('default_setting_list_style', 'email_style'));
        define('DEFAULT_IMAP_PER_PAGE', $config->get('default_setting_imap_per_page', 20));
        define('DEFAULT_JUNK_SINCE', $config->get('default_setting_junk_since', '-1 week'));
        define('DEFAULT_JUNK_PER_SOURCE', $config->get('default_setting_junk_per_source', 20));
        define('DEFAULT_TAGS_SINCE', $config->get('default_setting_tags_since', '-1 week'));
        define('DEFAULT_TAGS_PER_SOURCE', $config->get('default_setting_tags_per_source', 20));
        define('DEFAULT_TRASH_SINCE', $config->get('default_setting_trash_since', '-1 week'));
        define('DEFAULT_TRASH_PER_SOURCE', $config->get('default_setting_trash_per_source', 20));
        define('DEFAULT_DRAFT_SINCE', $config->get('default_setting_draft_since', '-1 week'));
        define('DEFAULT_DRAFT_PER_SOURCE', $config->get('default_setting_draft_per_source', 20));
        define('DEFAULT_SIMPLE_MSG_PARTS', $config->get('default_setting_simple_msg_parts', false));
        define('DEFAULT_MSG_PART_ICONS', $config->get('default_setting_msg_part_icons', true));
        define('DEFAULT_PAGINATION_LINKS', $config->get('default_setting_pagination_links', true));
        define('DEFAULT_TEXT_ONLY', $config->get('default_setting_text_only', true));
        define('DEFAULT_NO_PASSWORD_SAVE', $config->get('default_setting_no_password_save', false));
        define('DEFAULT_SHOW_LIST_ICONS', $config->get('default_setting_show_list_icons', true));
        define('DEFAULT_START_PAGE', $config->get('default_setting_start_page', "none"));
        define('DEFAULT_DISABLE_DELETE_PROMPT', $config->get('default_setting_disable_delete_prompt', false));
        define('DEFAULT_NO_FOLDER_ICONS', $config->get('default_setting_no_folder_icons', false));
        define('DEFAULT_SETTING_LANGUAGE', $config->get('default_setting_language', 'en'));
        define('DEFAULT_SMTP_COMPOSE_TYPE', $config->get('default_setting_smtp_compose_type', 0));
        define('DEFAULT_SMTP_AUTO_BCC', $config->get('default_setting_smtp_auto_bcc', false));
        define('DEFAULT_THEME', $config->get('default_setting_theme', 'default'));
        define('DEFAULT_UNREAD_EXCLUDE_WORDPRESS', $config->get('default_setting_unread_exclude_wordpress', false));
        define('DEFAULT_WORDPRESS_SINCE', $config->get('default_setting_wordpress_since', '-1 week'));
        define('DEFAULT_UNREAD_EXCLUDE_GITHUB', $config->get('default_setting_unread_exclude_github', false));
        define('DEFAULT_GITHUB_PER_SOURCE', $config->get('default_setting_github_limit', 20));
        define('DEFAULT_GITHUB_SINCE', $config->get('default_setting_github_since', '-1 week'));
        define('DEFAULT_INLINE_MESSAGE', $config->get('default_setting_inline_message', false));
        define('DEFAULT_INLINE_MESSAGE_STYLE', $config->get('default_setting_inline_message_style', 'right'));
        define('DEFAULT_ENABLE_KEYBOARD_SHORTCUTS', $config->get('default_setting_enable_keyboard_shortcuts', false));
        define('DEFAULT_ENABLE_SIEVE_FILTER', $config->get('default_setting_enable_sieve_filter', false));
    }

    /**
     * Sets required environment variables that are used within .env files
     */
    private function set_required_environment_variables($file) {
        $_ENV['CYPHT_DOTENV'] = APP_PATH . $file;
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
