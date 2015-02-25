<?php

/**
 * WordPress modules
 * @package modules
 * @subpackage wordpress
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_process_wordpress_authorization extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('state', $this->request->get) && $this->request->get['state'] == 'wp_authorization') {
            if (array_key_exists('code', $this->request->get)) {
                $details = wp_connect_details($this->config);
                $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
                $result = $oauth2->request_token($details['token_url'], $this->request->get['code']);
                if (!empty($result) && array_key_exists('access_token', $result)) {
                    Hm_Msgs::add('WordPress.com connection established');
                    $this->user_config->set('wp_connect_details', $result);
                    $user_data = $this->user_config->dump();
                    if (!empty($user_data)) {
                        $this->session->set('user_data', $user_data);
                    }
                    $this->session->record_unsaved('WordPress.com connection');
                    $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
                    $this->session->close_early();
                }
                else {
                    Hm_Msgs::add('ERRAn Error Occured');
                }
            }
            elseif (array_key_exists('error', $this->request->get)) {
                Hm_Msgs::add('ERR'.ucwords(str_replace('_', ' ', $this->request->get['error'])));
            }
            else {
                Hm_Msgs::add('ERRAn Error Occured');
            }
            $msgs = Hm_Msgs::get();
            $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(serialize($msgs)), 0);
            Hm_Router::page_redirect('?page=servers');
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_setup_wordpress_connect extends Hm_Handler_Module {
    public function process() {
        $details = wp_connect_details($this->config);
        $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
        $this->out('wp_auth_url', $oauth2->request_authorization_url($details['auth_url'], 'global', 'wp_authorization'));
        $this->out('wp_connect_details', $this->user_config->get('wp_connect_details', array()));
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_wordpress_folders extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_wp_notifications"><a class="unread_link" href="?page=wordpress_notifications&list_path=wp_notifications">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$env_closed).
            '" alt="" width="16" height="16" /> '.$this->trans('Notifications').'</a></li>';
        $this->append('folder_sources', 'wordPress_folders');
        Hm_Page_Cache::add('wordPress_folders', $res, true);
        return '';
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_wordpress_connect_section extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('wp_connect_details', array());
        $res = '<div class="wordpress_connect"><div data-target=".wordpress_connect_section" class="server_section">'.
            '<img src="'.Hm_Image_Sources::$key.'" alt="" width="16" height="16" /> '.
            $this->trans('WordPress.com Connect').'</div><div class="wordpress_connect_section">'.
            'Connect to WordPress.com to view your notifications.<br /><br />';
        if (empty($details)) {
            $res .= '<a href="'.$this->get('wp_auth_url', '').'">Enable</a></div></div>';
        }
        else {
            $res .= 'Already connected</div></div>';
        }
        return $res;
    }
}

/**
 * @subpackage wordpress/functions
 */
function wp_connect_details($config) {
    $details = array (
        'auth_url' => 'https://public-api.wordpress.com/oauth2/authorize',
        'token_url' => 'https://public-api.wordpress.com/oauth2/token',
    );
    $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/wordpress.ini';
    if (is_readable($ini_file)) {
        $settings = parse_ini_file($ini_file);
        if (!empty($settings)) {
            $details['client_id'] = $settings['client_id'];
            $details['client_secret'] = $settings['client_secret'];
            $details['redirect_uri'] = $settings['client_uri'];
        }
    }
    return $details;
}

/**
 * @subpackage wordpress/functions
 */
function wp_get_notifications($details) {
    //$details = $this->user_config->get('wp_connect_details', array());
    $result = array();
    $url = 'https://public-api.wordpress.com/rest/v1/notifications/?number=20';
    $headers = array('Authorization: Bearer ' . $details['access_token']);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $curl_result = curl_exec($ch);
    if (substr($curl_result, 0, 1) == '{') {
        $result = @json_decode($curl_result, true);
    }
    return $result;
}

?>
