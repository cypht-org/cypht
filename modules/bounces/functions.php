<?php

/**
 * Bounce handling helpers
 * @package modules
 * @subpackage bounces
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/bounces/hm-bounce-policy.php';
require_once APP_PATH.'modules/bounces/hm-bounce-parser.php';
require_once APP_PATH.'modules/bounces/hm-bounce-store.php';
require_once APP_PATH.'modules/bounces/hm-bounce-service.php';

if (!hm_exists('get_bounce_store')) {
    /**
     * Bounce store for the current user, the "bounce_store_class" site config value replaces the class
     * @return Hm_Bounce_Store
     */
    function get_bounce_store($site_config, $user_config) {
        $class = 'Hm_Bounce_Store_User_Config';
        if ($site_config && $site_config->get('bounce_store_class')) {
            $class = $site_config->get('bounce_store_class');
        }
        return new $class($user_config);
    }
}

if (!hm_exists('get_bounce_service')) {
    /**
     * Bounce service configured from the bounce_rules, bounce_hard_weight and
     * bounce_reset_after_days site config values
     * @return Hm_Bounce_Service
     */
    function get_bounce_service($site_config, $user_config) {
        $options = [];
        if ($site_config) {
            $options = [
                'rules' => $site_config->get('bounce_rules', Hm_Bounce_Policy::DEFAULT_RULES),
                'hard_weight' => $site_config->get('bounce_hard_weight', 1),
                'reset_after' => (int) $site_config->get('bounce_reset_after_days', 0) * 86400,
            ];
        }
        return new Hm_Bounce_Service(get_bounce_store($site_config, $user_config), $options);
    }
}

if (!hm_exists('bounce_headers_look_like_bounce')) {
    /**
     * Cheap header check done before downloading and parsing a message
     * @param array $headers message headers
     * @return bool
     */
    function bounce_headers_look_like_bounce(array $headers) {
        $headers = array_change_key_case($headers, CASE_LOWER);
        if (array_key_exists('x-failed-recipients', $headers)) {
            return true;
        }
        $contentType = is_array($headers['content-type'] ?? '') ? implode(' ', $headers['content-type']) : (string) ($headers['content-type'] ?? '');
        if (mb_stripos($contentType, 'delivery-status') !== false) {
            return true;
        }
        $from = is_array($headers['from'] ?? '') ? implode(' ', $headers['from']) : (string) ($headers['from'] ?? '');
        return (bool) preg_match('/mailer-daemon|postmaster|mail delivery/i', $from);
    }
}

if (!hm_exists('bounce_condition_matches')) {
    /**
     * Evaluate the "Is bounce" filter condition
     * @param array $events bounce events of the message, from Hm_Bounce_Parser::parse
     * @param string $type Soft, Hard, !Soft or !Hard
     * @return bool
     */
    function bounce_condition_matches(array $events, $type) {
        $negate = mb_substr($type, 0, 1) === '!';
        $wanted = mb_strtolower(ltrim($type, '!'));
        $found = false;
        foreach ($events as $event) {
            if ($event['type'] === $wanted) {
                $found = true;
                break;
            }
        }
        return $negate ? !$found : $found;
    }
}
