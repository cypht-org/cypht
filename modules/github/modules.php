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
                if (!empty($result) && array_key_exists('access_token', $result)) {
                    Hm_Msgs::add('Github connection established');
                    $this->user_config->set('github_connect_details', $result);
                    $user_data = $this->user_config->dump();
                    $this->session->set('user_data', $user_data);
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
            $this->out('github_auth_url', $oauth2->request_authorization_url($details['auth_url'], 'repo', 'github_authorization'));
            $this->out('github_connect_details', $this->user_config->get('github_connect_details', array()));
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_process_add_repo extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('new_github_repo', 'new_github_repo_owner', 'github_add_repo'));
        if ($success) {
            $details = $this->user_config->get('github_connect_details');
            if ($details) {
                $url = sprintf('https://api.github.com/repos/%s/%s/events?page=1&per_page=1', urlencode($form['new_github_repo_owner']), urlencode($form['new_github_repo']));
                $data = github_fetch_content($details, $url);
                if (!empty($data)) {
                    $repos = $this->user_config->get('github_repos', array());
                    $new_repo = urlencode($form['new_github_repo_owner']).'/'.urlencode($form['new_github_repo']);
                    if (!in_array($new_repo, $repos, true)) {
                        $repos[] = $new_repo;
                        $this->user_config->set('github_repos', $repos);
                        $user_data = $this->user_config->dump();
                        $this->session->set('user_data', $user_data);
                        $this->session->record_unsaved('Github repository added');
                        Hm_Msgs::add('Added repository');
                    }
                    else {
                        Hm_Msgs::add('ERRRepository is already added');
                    }
                }
                else {
                    Hm_Msgs::add('ERRCould not find that repository/owner combination at github.com');
                }
            }
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
            $this->session->set('user_data', $user_data);
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
class Hm_Handler_github_list_data extends Hm_Handler_Module {
    public function process() {
        $details = $this->user_config->get('github_connect_details');
        if ($details) {
            $url = 'https://api.github.com/repos/jasonmunro/hm3/events?page=1&per_page=25';
            $this->out('github_data', github_fetch_content($details, $url));
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
                $this->out('mailbox_list_title', array('Github', 'cypht'));
                $this->append('data_sources', array('callback' => 'load_github_data', 'type' => 'github', 'name' => 'Github', 'id' => 0));
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
            $res = '<li class="menu_github_all"><a class="unread_link" href="?page=message_list&list_path=github_all">'.
                '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).
                '" alt="" width="16" height="16" /> '.$this->trans('Cypht').'</a></li>';

            $this->append('folder_sources', 'github_folders');
            Hm_Page_Cache::add('github_folders', $res, true);
        }
        return '';
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_filter_github_data extends Hm_Output_Module {
    protected function output() {
        $res = array();
        foreach ($this->get('github_data', array()) as $event) {
            $id = $event['id'];
            $subject = build_github_subject($event, $this);
            $url = '';
            $from = $event['actor']['login'];
            $ts = strtotime($event['created_at']);
            $date = date('r', $ts);
            $style = $this->get('news_list_style') ? 'news' : 'email';
            if ($this->get('is_mobile')) {
                $style = 'news';
            }
            if ($style == 'news') {
                $res[$id] = message_list_row(array(
                        array('checkbox_callback', $id),
                        array('icon_callback', array()),
                        array('subject_callback', $subject, $url, array()),
                        array('safe_output_callback', 'source', 'Github'),
                        array('safe_output_callback', 'from', $from),
                        array('date_callback', human_readable_interval($date), $ts),
                    ),
                    $id,
                    $style,
                    $this
                );
            }
            else {
                $res[$id] = message_list_row(array(
                        array('checkbox_callback', $id),
                        array('safe_output_callback', 'source', 'Github'),
                        array('safe_output_callback', 'from', $from),
                        array('subject_callback', $subject, $url, array()),
                        array('date_callback', human_readable_interval($date), $ts),
                        array('icon_callback', array())
                    ),
                    $id,
                    $style,
                    $this
                );
            }
        }
        $this->out('formatted_message_list', $res);
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_add_repo extends Hm_Output_Module {
    protected function output() {
        $res = '';
        if (!empty($this->get('github_connect_details', array()))) {
            $res = '<div class="configured_server"><div class="subtitle">'.$this->trans('Add a Repository').'</div>'.
                '<form method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<label class="screen_reader">'.$this->trans('Name').'</label>'.
                '<input type="text" value="" placeholder="'.$this->trans('Name').'" name="new_github_repo" />'.
                '<label class="screen_reader">'.$this->trans('Owner').'</label>'.
                '<input type="text" value="" placeholder="'.$this->trans('Owner').'" name="new_github_repo_owner" />'.
                '<input type="submit" name="github_add_repo" value="Add" />'.
                '</form></div>';
        }
        return $res.'</div></div>';
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
            $this->trans('Github Connect').'</div><div class="github_connect_section"><div class="add_server">';
        if (empty($details)) {
            $res .= 'Connect to Github<br /><br />';
            $res .= '<a href="'.$this->get('github_auth_url', '').'">'.$this->trans('Enable').'</a></div></div>';
        }
        else {
            $res .= $this->trans('Already connected');
            $res .= '<br /><form method="POST">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="submit" name="github_disconnect" class="github_disconnect" value="'.$this->trans('Disconnect').'" />';
            $res .= '</form>';
        }
        return $res.'</div>';
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

/**
 * @subpackage github/functions
 */
function build_github_subject($event, $output_mod) {
    $pre = '['.trim(str_replace('Event', '', trim(preg_replace("/([A-Z])/", " $1", $event['type'])))).']';
    $post = '';
    switch (strtolower($event['type'])) {
        case 'issuecommentevent':
            $post = $event['payload']['issue']['title'];
            break;
        case 'issuesevent':
            $post = $event['payload']['issue']['title'];
            break;
        case 'pushevent':
            if (count($event['payload']['commits']) > 1) {
                $post = sprintf($output_mod->trans('%d new changes committed'), count($event['payload']['commits']));
            }
            else {
                $post = sprintf($output_mod->trans('%d new change committed'), count($event['payload']['commits']));
            }
            break;
        case 'watchevent':
            if ($event['payload']['action'] == 'started') {
                $post  .= sprintf($output_mod->trans('%s started watching this repo'), $event['actor']['login']);
            }
            else {
                $post  .= sprintf($output_mod->trans('%s stopped watching this repo'), $event['actor']['login']);
            }
            break;
        default:
            break;
    }
    return $pre.' '.$post;
}
/**
 * @subpackage github/functions
 */
function github_fetch_content($details, $url) {
    $result = array();
    $headers = array('Authorization: token ' . $details['access_token']);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'hm3');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $curl_result = curl_exec($ch);
    if (substr($curl_result, 0, 1) == '[') {
        $result = @json_decode($curl_result, true);
    }
    return $result;
}

?>
