<?php

/**
 * Github modules
 * @package modules
 * @subpackage github
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_folders_data extends Hm_Handler_Module {
    public function process() {
        $this->out('github_connect_details', $this->user_config->get('github_connect_details', array()));
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_process_github_authorization extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('state', $this->request->get) && $this->request->get['state'] == 'github_authorization') {
            if (array_key_exists('code', $this->request->get)) {
                $details = github_connect_details($this->config);
                $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
                $result = $oauth2->request_token($details['token_url'], $this->request->get['code']);
                elog($result);
                if (!empty($result) && array_key_exists('access_token', $result)) {
                    Hm_Msgs::add('Github connection established');
                    $this->user_config->set('github_connect_details', $result);
                    $user_data = $this->user_config->dump();
                    if (!empty($user_data)) {
                        $this->session->set('user_data', $user_data);
                    }
                    $this->session->record_unsaved('Github connection');
                    $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
                    $this->session->close_early();
                }
                else {
                    Hm_Msgs::add('ERRAn Error Occurred');
                }
            }
            elseif (array_key_exists('error', $this->request->get)) {
                Hm_Msgs::add('ERR'.ucwords(str_replace('_', ' ', $this->request->get['error'])));
            }
            else {
                Hm_Msgs::add('ERRAn Error Occurred');
            }
            $msgs = Hm_Msgs::get();
            $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(serialize($msgs)), 0);
            Hm_Dispatch::page_redirect('?page=servers');
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_setup_github_connect extends Hm_Handler_Module {
    public function process() {
        $details = github_connect_details($this->config);
        if (!empty($details)) {
            $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
            $this->out('github_auth_url', $oauth2->request_authorization_url($details['auth_url'], false, 'github_authorization'));
            $this->out('github_connect_details', $this->user_config->get('github_connect_details', array()));
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_disconnect extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('github_disconnect', $this->request->post)) {
            $this->user_config->set('github_connect_details', array());
            $user_data = $this->user_config->dump();
            if (!empty($user_data)) {
                $this->session->set('user_data', $user_data);
            }
            $this->out('reload_folders', true, false);
            $this->session->record_unsaved('Github connection deleted');
            Hm_Msgs::add('Github connection deleted');
            Hm_Page_Cache::flush($this->session);
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_list_type extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if ($path == 'github_all') {
                $this->out('list_path', 'github_all');
                $this->out('list_parent', 'github_all');
                $this->out('mailbox_list_title', array('Github'));
                $this->append('data_sources', array('callback' => 'load_github_all_data', 'type' => 'github', 'name' => 'Github', 'id' => 0));
            }
        }
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_folders extends Hm_Output_Module {
    protected function output() {
        if (!empty($this->get('github_connect_details', array()))) {
            $res = '<li class="menu_hn_trending"><a class="unread_link" href="?page=message_list&list_path=github_all">'.
                '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).
                '" alt="" width="16" height="16" /> '.$this->trans('All').'</a></li>';

            $this->append('folder_sources', 'github_folders');
            Hm_Page_Cache::add('github_folders', $res, true);
        }
        return '';
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_connect_section extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('github_connect_details', array());
        $res = '<div class="github_connect"><div data-target=".github_connect_section" class="server_section">'.
            '<img src="'.Hm_Image_Sources::$code.'" alt="" width="16" height="16" /> '.
            $this->trans('Github Connect').'</div><div class="github_connect_section">';
        if (empty($details)) {
            $res .= 'Connect to Github<br /><br />';
            $res .= '<a href="'.$this->get('github_auth_url', '').'">'.$this->trans('Enable').'</a></div></div>';
        }
        else {
            $res .= $this->trans('Already connected');
            $res .= '<br /><form method="POST">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="submit" name="github_disconnect" class="github_disconnect" value="'.$this->trans('Disconnect').'" />';
            $res .= '</form></div></div>';
        }
        return $res;
    }
}

/**
 * @subpackage github/functions
 */
function github_connect_details($config) {
    $details = array();
    $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/github.ini';
    if (is_readable($ini_file)) {
        $settings = parse_ini_file($ini_file);
        if (!empty($settings)) {
            $details = $settings;
        }
    }
    return $details;
}

?>
