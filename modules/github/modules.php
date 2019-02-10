<?php

/**
 * Github modules
 * @package modules
 * @subpackage github
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Used to cache "read" github items
 * @subpackage github/lib
 */
class Hm_Github_Uid_Cache {
    use Hm_Uid_Cache;
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_process_github_limit_setting extends Hm_Handler_Module {
    public function process() {
        process_site_setting('github_limit', $this, 'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_process_github_since_setting extends Hm_Handler_Module {
    public function process() {
        process_site_setting('github_since', $this, 'since_setting_callback');
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_process_unread_github_included extends Hm_Handler_Module {
    public function process() {
        function unread_github_setting_callback($val) { return $val; }
        process_site_setting('unread_exclude_github', $this, 'unread_github_setting_callback', false, true);
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_status extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('github_repo'));
        if ($success) {
            $details = $this->user_config->get('github_connect_details', array());
            if (!empty($details)) {
                $repos = $this->user_config->get('github_repos', array());
                if (in_array($form['github_repo'], $repos, true)) {
                    $url = sprintf('https://api.github.com/repos/%s/events?page=1&per_page=1', $form['github_repo']);
                    $start = microtime(true);
                    $api = new Hm_API_Curl();
                    $data = $api->command($url, array('Authorization: token ' . $details['access_token']));
                    if (!empty($data)) {
                        $this->out('github_status', 'success');
                        $this->out('github_connect_time', (microtime(true) - $start));
                        $this->out('github_repo', $form['github_repo']);
                        return;
                    }
                }
            }
            $this->out('github_status', 'failed');
            $this->out('github_repo', $form['github_repo']);
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_load_github_repos extends Hm_Handler_Module {
    public function process() {
        $this->out('github_repos', $this->user_config->get('github_repos', array()));
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_message_action extends Hm_Handler_Module {
    public function process() {

        list($success, $form) = $this->process_form(array('action_type', 'message_ids'));
        if ($success) {
            $id_list = explode(',', $form['message_ids']);
            Hm_Github_Uid_Cache::load($this->cache->get('github_read_uids', array(), true));
            foreach ($id_list as $msg_id) {
                if (preg_match("/^github_(\d)+_(\d)+$/", $msg_id)) {
                    $parts = explode('_', $msg_id, 3);
                    $guid = $parts[2];
                    switch($form['action_type']) {
                        case 'unread':
                            Hm_Github_Uid_Cache::unread($guid);
                            break;
                        case 'read':
                        case 'delete':
                            Hm_Github_Uid_Cache::read($guid);
                            break;
                    }
                }
            }
            $this->cache->set('github_read_uids', Hm_Github_Uid_Cache::dump(), 0, true);
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_folders_data extends Hm_Handler_Module {
    public function process() {
        $this->out('github_connect_details', $this->user_config->get('github_connect_details', array()));
        $this->out('github_repos', $this->user_config->get('github_repos', array()));
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_event_detail extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('list_path', 'github_uid'));
        if ($success) {
            Hm_Github_Uid_Cache::load($this->cache->get('github_read_uids', array(), true));
            $details = $this->user_config->get('github_connect_details', array());
            $repos = $this->user_config->get('github_repos', array());
            $limit = $this->user_config->get('github_limit_setting', DEFAULT_PER_SOURCE);
            $repo = substr($form['list_path'], 7);
            if (in_array($repo, $repos, true)) {
                $url = sprintf('https://api.github.com/repos/%s/events?page=1&per_page='.$limit, $repo);
                $api = new Hm_API_Curl();
                $data = $api->command($url, array('Authorization: token ' . $details['access_token']));
                $event = array();
                $uid = substr($form['github_uid'], 9);
                if (is_array($data)) {
                    foreach ($data as $item) {
                        if ($item['id'] == $uid) {
                            $event = $item;
                            break;
                        }
                    }
                }
                Hm_Github_Uid_Cache::read($item['id']);
                $this->cache->set('github_read_uids', Hm_Github_Uid_Cache::dump(), 0, true);
                $this->out('github_event_detail', $event);
            }
        }
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
                $result = $oauth2->request_token($details['token_url'], $this->request->get['code'], array('Accept: application/json'));
                if (count($result) > 0 && array_key_exists('access_token', $result)) {
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
            $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(serialize($msgs)));
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
            $this->out('github_repos', $this->user_config->get('github_repos', array()));
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_process_remove_repo extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('github_repo', 'github_remove_repo'));
        if ($success) {
            $details = $this->user_config->get('github_connect_details');
            if ($details) {
                $repos = $this->user_config->get('github_repos', array());
                if (in_array($form['github_repo'], $repos, true)) {
                    foreach ($repos as $index => $repo) {
                        if ($repo === $form['github_repo']) {
                            unset($repos[$index]);
                            break;
                        }
                    }
                    $this->user_config->set('github_repos', $repos);
                    $user_data = $this->user_config->dump();
                    $this->session->set('user_data', $user_data);
                    $this->session->record_unsaved('Github repository removed');
                    $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
                    Hm_Msgs::add('Removed repository');
                }
            }
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
                $api = new Hm_API_Curl();
                $data = $api->command($url, array('Authorization: token ' . $details['access_token']));
                if (!empty($data)) {
                    $repos = $this->user_config->get('github_repos', array());
                    $new_repo = urlencode($form['new_github_repo_owner']).'/'.urlencode($form['new_github_repo']);
                    if (!in_array($new_repo, $repos, true)) {
                        $repos[] = $new_repo;
                        $this->user_config->set('github_repos', $repos);
                        $user_data = $this->user_config->dump();
                        $this->session->set('user_data', $user_data);
                        $this->session->record_unsaved('Github repository added');
                        $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
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
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_list_data extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('github_repo'));
        if ($success) {
            $login_time = $this->session->get('login_time', false);
            if ($login_time) {
                $this->out('login_time', $login_time);
            }
            if (array_key_exists('list_path', $this->request->get)) {
                if (in_array($this->request->get['list_path'], array('combined_inbox', 'unread'), true)) {
                    $this->out('list_parent', $this->request->get['list_path']);
                }
            }
            Hm_Github_Uid_Cache::load($this->cache->get('github_read_uids', array(), true));
            $details = $this->user_config->get('github_connect_details');
            $repos = $this->user_config->get('github_repos');
            if (in_array($form['github_repo'], $repos, true) && $details) {
                $limit = $this->user_config->get('github_limit_setting', DEFAULT_PER_SOURCE);
                $url = sprintf('https://api.github.com/repos/%s/events?page=1&per_page='.$limit, $form['github_repo']);
                $api = new Hm_API_Curl();
                $this->out('github_data', $api->command($url, array('Authorization: token ' . $details['access_token'])));
                $this->out('github_data_source', $form['github_repo']);
                $this->out('github_data_source_id', array_search($form['github_repo'], $repos, true));
            }
            if (array_key_exists('list_path', $this->request->get)) {
                if ($this->request->get['list_path'] == 'unread') {
                    $this->out('github_list_since', process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE)));
                }
                if ($this->request->get['list_path'] == 'combined_inbox') {
                    $this->out('github_list_since', process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE)));
                }
                if ($this->request->get['list_path'] == 'github_all') {
                    $this->out('github_list_since', process_since_argument($this->user_config->get('github_since_setting', DEFAULT_SINCE)));
                }
            }
            if (array_key_exists('github_unread', $this->request->post) && $this->request->post['github_unread']) {
                $this->out('github_unread', true);
            }
        }
    }
}

/**
 * @subpackage github/handler
 */
class Hm_Handler_github_list_type extends Hm_Handler_Module {
    public function process() {
        $repos = $this->user_config->get('github_repos', array());
        $excluded = $this->user_config->get('unread_exclude_github_setting', false);
        $github_list = false;
        $parent = '';
        if (array_key_exists('list_parent', $this->request->get)) {
            $parent = $this->request->get['list_parent'];
        }
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if ($this->page == 'message_list' && preg_match("/^github_(.+)$/", $path)) {
                $github_list = true;
                if ($path == 'github_all') {
                    $this->out('list_path', 'github_all', false);
                    $this->out('list_parent', $parent, false);
                    $this->out('mailbox_list_title', array('Github', 'All'));
                    $this->out('message_list_since', $this->user_config->get('github_since_setting', DEFAULT_SINCE));
                    $this->out('per_source_limit', $this->user_config->get('github_limit_setting', DEFAULT_PER_SOURCE));
                    foreach ($repos as $repo) {
                        $this->append('data_sources', array('callback' => 'load_github_data', 'type' => 'github', 'name' => $repo, 'id' => $repo));
                    }
                }
                else {
                    $repo = substr($path, 7);
                    $this->out('list_parent', $parent, false);
                    if ($parent == 'github_all') {
                        $this->out('mailbox_list_title', array(urldecode($repo)));
                    }
                    else {
                        $this->out('mailbox_list_title', array('Github', urldecode($repo)));
                        $this->out('list_path', $path, false);
                    }
                    $this->out('custom_list_controls', ' ');
                    $this->append('data_sources', array('callback' => 'load_github_data', 'type' => 'github', 'name' => $repo, 'id' => $repo));
                }
            }
            elseif ($this->page == 'message_list' && ($path == 'combined_inbox' || $path == 'unread')) {
                $github_list = true;
                if (!$excluded || $path == 'combined_inbox') {
                    foreach ($repos as $repo) {
                        $this->append('data_sources', array('callback' => 'load_github_data', 'type' => 'github', 'name' => $repo, 'id' => $repo));
                    }
                }
            }
            elseif ($this->page == 'message' && preg_match("/^github_(.+)$/", $path)) {
                if (!$parent) {
                    $repo = substr($path, 7);
                    $this->out('mailbox_list_title', array('Github', urldecode($repo)));
                }
            }
        }
        if (!$github_list) {
            foreach ($repos as $repo) {
                if (!$excluded) {
                    $this->append('data_sources', array('callback' => 'load_github_data_background', 'group' => 'background', 'type' => 'github', 'name' => 'Github', 'id' => $repo));
                }
            }
        }
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_folders extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('github_connect_details', array());
        if (!empty($details)) {
            $res = '<li class="menu_github_all"><a class="unread_link" href="?page=message_list&list_path=github_all">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).'" alt="" width="16" height="16" /> ';
            }
            $res .= $this->trans('All').'</a></li>';
            foreach ($this->get('github_repos', array()) as $repo) {
                $res .= '<li class="menu_github_'.$this->html_safe($repo).'"><a class="unread_link" href="?page=message_list&list_path=github_'.$this->html_safe($repo).'">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).'" alt="" width="16" height="16" /> ';
                }
                $res .= $this->html_safe(explode('/', urldecode($repo))[1]).'</a></li>';
            }
            $this->append('folder_sources', array('github_folders', $res));
        }
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_filter_github_data extends Hm_Output_Module {
    protected function output() {
        $res = array();
        $login_time = false;
        $unread_only = false;
        $show_icons = $this->get('msg_list_icons');
        if ($this->get('login_time')) {
            $login_time = $this->get('login_time');
        }
        if ($this->get('list_path', '') == 'unread' || $this->get('github_unread', false)) {
            $unread_only = true;
        }
        $repo_id = $this->get('github_data_source_id');
        $repo = $this->get('github_data_source', 'Github');
        $repo_parts = explode('/', $repo);
        $repo_name = $repo_parts[1];
        $cutoff = $this->get('github_list_since', '');
        if ($cutoff) {
            $cutoff = strtotime($cutoff);
        }
        else {
            $cutoff = 0;
        }
        $list_parent = false;
        if ($this->get('list_parent', '')) {
            $list_parent = $this->get('list_parent');
        }
        elseif ($this->get('list_path') == 'github_all') {
            $list_parent = $this->get('list_path');
        }
        foreach ($this->get('github_data', array()) as $event) {
            if (!is_array($event) || !array_key_exists('id', $event)) {
                continue;
            }
            $row_class = 'github';
            $id = 'github_'.$repo_id.'_'.$event['id'];
            $subject = build_github_subject($event, $this);
            $url = '?page=message&uid='.$this->html_safe($id).'&list_path=github_'.$this->html_safe($repo);
            if ($list_parent) {
                $url .= '&list_parent='.$this->html_safe($list_parent);
            }
            $from = $event['actor']['login'];
            $ts = strtotime($event['created_at']);
            if ($ts < $cutoff) {
                continue;
            }
            if (Hm_Github_Uid_Cache::is_read($event['id'])) {
                $flags = array();
            }
            elseif (Hm_Github_Uid_Cache::is_unread($event['id'])) {
                $flags = array('unseen');
                $row_class .= ' unseen';
            }
            elseif ($ts && $login_time && $ts <= $login_time) {
                $flags = array();
            }
            else {
                $row_class .= ' unseen';
                $flags = array('unseen');
            }
            if ($unread_only && !in_array('unseen', $flags)) {
                continue;
            }
            $date = date('r', $ts);
            $style = $this->get('news_list_style') ? 'news' : 'email';
            if ($this->get('is_mobile')) {
                $style = 'news';
            }
            $row_class .= ' '.$repo_name;
            $icon = 'code';
            if (!$show_icons) {
                $icon = '';
            }
            if ($style == 'news') {
                $res[$id] = message_list_row(array(
                        array('checkbox_callback', $id),
                        array('icon_callback', $flags),
                        array('subject_callback', $subject, $url, $flags, $icon),
                        array('safe_output_callback', 'source', $repo_name),
                        array('safe_output_callback', 'from', $from),
                        array('date_callback', human_readable_interval($date), $ts),
                    ),
                    $id,
                    $style,
                    $this,
                    $row_class
                );
            }
            else {
                $res[$id] = message_list_row(array(
                        array('checkbox_callback', $id),
                        array('safe_output_callback', 'source', $repo_name, $icon),
                        array('safe_output_callback', 'from', $from),
                        array('subject_callback', $subject, $url, $flags),
                        array('date_callback', human_readable_interval($date), $ts),
                        array('icon_callback', $flags)
                    ),
                    $id,
                    $style,
                    $this,
                    $row_class
                );
            }
        }
        $this->out('formatted_message_list', $res);
        $this->out('github_server_id', $repo_id);
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_add_repo extends Hm_Output_Module {
    protected function output() {
        $res = '';
        $details = $this->get('github_connect_details', array());
        if (!empty($details)) {
            $res = '<div class="configured_server"><div class="subtitle">'.$this->trans('Add a Repository').'</div>'.
                '<form method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<label class="screen_reader">'.$this->trans('Name').'</label>'.
                '<input type="text" value="" placeholder="'.$this->trans('Name').'" name="new_github_repo" />'.
                '<label class="screen_reader">'.$this->trans('Owner').'</label>'.
                '<input type="text" value="" placeholder="'.$this->trans('Owner').'" name="new_github_repo_owner" />'.
                '<input type="submit" name="github_add_repo" value="Add" />'.
                '</form></div>';
            $res .= '<div class="configured_server"><div class="server_title">'.$this->trans('Repositories').'</div>';
            foreach ($this->get('github_repos', array()) as $repo) {
                $res .= '<div class="configured_repo"><form class="remove_repo" method="POST">'.
                    '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                    '<input type="hidden" name="github_repo" value="'.$this->html_safe($repo).'" />'.
                    '<input type="submit" name="github_remove_repo" value="'.$this->trans('Remove').'" class="github_remove_repo" />'.$this->html_safe($repo).'</form></div>';
            }
            $res .= '</div></div><div class="end_float"></div></div>';
        }
        return $res;
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_filter_github_event_detail extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('github_event_detail', array());
        if (!empty($details)) {
            if (array_key_exists('payload', $details)) {
                $body = github_parse_payload($details, $this);
                $headers = github_parse_headers($details, $this);
            }
            $this->out('github_msg_text', $headers.$body);
        }
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_filter_github_status extends Hm_Output_Module {
    protected function output() {
        if ($this->get('github_status') == 'success') {
            $this->out('github_status_display', '<span class="online">'.$this->trans('Connected').'</span> in '.round($this->get('github_connect_time'), 3));
        }
        else {
            $this->out('github_status_display', '<span class="down">'.$this->trans('Down').'</span>');
        }
        $this->out('github_status_repo', $this->get('github_repo', ''));
    }
};

/**
 * @subpackage github/output
 */
class Hm_Output_display_github_status extends Hm_Output_Module {
    protected function output() {
        $res = '';
        $repos = $this->get('github_repos', array());
        if (!empty($repos)) {
            foreach ($repos as $repo) {
                $res .= '<tr><td class="github_repo" data-id="'.$this->html_safe($repo).'">'.$this->trans('Github repo').'</td><td>'.$this->html_safe($repo).'</td><td class="github_'.$this->html_safe($repo).'"></td></tr>';
            }
        }
        return $res;
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
            $res .= '<a href="'.$this->get('github_auth_url', '').'">'.$this->trans('Enable').'</a></div></div><div class="end_float"</div>';
        }
        else {
            $res .= $this->trans('Already connected');
            $res .= '<br /><form id="github_disconnect_form" method="POST">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="submit" name="github_disconnect" class="github_disconnect" value="'.$this->trans('Disconnect').'" />';
            $res .= '</form>';
        }
        return $res.'</div>';
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_unread_github_included_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('unread_exclude_github', $settings) && $settings['unread_exclude_github']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        return '<tr class="unread_setting"><td><label for="unread_exclude_github">'.$this->trans('Exclude unread Github notices').'</label></td>'.
            '<td><input type="checkbox" '.$checked.' value="1" id="unread_exclude_github" name="unread_exclude_github" /></td></tr>';
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_start_github_settings extends Hm_Output_Module {
    protected function output() {
        return '<tr><td colspan="2" data-target=".github_all_setting" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$code.'" width="16" height="16" />'.$this->trans('Github Settings').'</td></tr>';
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_since_setting extends Hm_Output_Module {
    protected function output() {
        $since = DEFAULT_SINCE;
        $settings = $this->get('user_settings');
        if (array_key_exists('github_since', $settings) && $settings['github_since']) {
            $since = $settings['github_since'];
        }
        return '<tr class="github_all_setting"><td><label for="github_since">'.$this->trans('Show Github notices received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'github_since', $this).'</td></tr>';
    }
}

/**
 * @subpackage github/output
 */
class Hm_Output_github_limit_setting extends Hm_Output_Module {
    protected function output() {
        $limit = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings');
        if (array_key_exists('github_limit', $settings)) {
            $limit = $settings['github_limit'];
        }
        return '<tr class="github_all_setting"><td><label for="github_limit">'.$this->trans('Max Github notices per repository').'</label></td>'.
            '<td><input type="text" id="github_limit" name="github_limit" size="2" value="'.$this->html_safe($limit).'" /></td></tr>';
    }
}

/**
 * @subpackage github/functions
 */
if (!hm_exists('github_connect_details')) {
function github_connect_details($config) {
    return get_ini($config, 'github.ini');
}}

/**
 * @subpackage github/functions
 */
if (!hm_exists('build_github_subject')) {
function build_github_subject($event, $output_mod) {
    $ref = '';
    if (array_key_exists('payload', $event) && array_key_exists('ref', $event['payload'])) {
        $ref = sprintf(' / %s', preg_replace("/^refs\/heads\//", '', $event['payload']['ref']));
    }
    $pre = '['.$output_mod->html_safe(trim(str_replace('Event', '', trim(preg_replace("/([A-Z])/", " $1", $event['type']))))).']';
    $post = '';
    $max = 100;
    switch (strtolower($event['type'])) {
        case 'issuecommentevent':
            $post = $event['payload']['issue']['title'];
            break;
        case 'issuesevent':
            $post = $event['payload']['issue']['title'].' - '.$event['payload']['action'];
            break;
        case 'pushevent':
            if (count($event['payload']['commits']) > 1) {
                $post .= sprintf($output_mod->trans('%d commits: '), count($event['payload']['commits']));
            }
            else {
                $post .= sprintf($output_mod->trans('%d commit: '), count($event['payload']['commits']));
            }
            $post .= substr($event['payload']['commits'][0]['message'], 0, $max);
            break;
        case 'watchevent':
            if ($event['payload']['action'] == 'started') {
                $post  .= sprintf($output_mod->trans('%s started watching this repo'), $event['actor']['login']);
            }
            else {
                $post  .= sprintf($output_mod->trans('%s stopped watching this repo'), $event['actor']['login']);
            }
            break;
        case 'forkevent':
            $post = sprintf($output_mod->trans("%s forked %s"), $event['actor']['login'], $event['repo']['name']);
            break;
        case 'createevent':
            $post = sprintf($output_mod->trans("%s repository created"), $event['repo']['name']);
            break;
        case 'pullrequestevent':
            $post = substr($event['payload']['pull_request']['body'], 0, $max);
            break;
        case 'commitcommentevent':
            $post = substr($event['payload']['comment']['body'], 0, $max);
            break;
        case 'releaseevent':
            $post = substr($event['payload']['release']['name'], 0, $max);
        default:
            break;
    }
    if ($ref) {
        $post .= $output_mod->html_safe($ref);
    }
    return $pre.' '.$post;
}}

/**
 * @subpackage github/functions
 */
if (!hm_exists('github_parse_headers')) {
function github_parse_headers($data, $output_mod) {
    $res = '<table class="msg_headers"><colgroup><col class="header_name_col"><col class="header_val_col"></colgroup>';
    if (array_key_exists('type', $data)) {
        $type = build_github_subject($data, $output_mod);
    }
    else {
        $type = '[Unknown Type]';
    }
    if (array_key_exists('created_at', $data)) {
        $date = sprintf("%s", date('r', strtotime($data['created_at'])));
    }
    else {
        $date = '[No date]';
    }
    $repo_link = '';
    $from_link = '';

    if (array_key_exists('actor', $data) && array_key_exists('login', $data['actor'])) {
        $from = $data['actor']['login'];
        $from_link = sprintf(' - <a href="https://github.com/%s">https://github.com/%s</a>', $output_mod->html_safe($from), $output_mod->html_safe($from));
    }
    else {
        $from = '[No From]';
    }
    if (array_key_exists('repo', $data) && array_key_exists('name', $data['repo'])) {
        $name = $data['repo']['name'];
        $repo_link = sprintf(' - <a href="https://github.com/%s">https://github.com/%s</a>', $output_mod->html_safe($name), $output_mod->html_safe($name));
    }
    else {
        $name = '[No Repo]';
    }
    $res .= '<tr class="header_subject"><th colspan="2">'.$output_mod->html_safe($type).'</th></tr>';
    $res .= '<tr class="header_date"><th>'.$output_mod->trans('Date').'</th><td>'.$output_mod->html_safe($date).'</td></tr>';
    $res .= '<tr class="header_from"><th>'.$output_mod->trans('Author').'</th><td>'.$output_mod->html_safe($from).$from_link.'</td></tr>';
    $res .= '<tr><th>'.$output_mod->trans('Repository').'</th><td>'.$output_mod->html_safe($name).$repo_link.'</td></tr>';
    $res .= '<tr><td></td><td></td></tr></table>';
    return $res;
}}

/**
 * @subpackage github/functions
 */
if (!hm_exists('github_parse_payload')) {
function github_parse_payload($data, $output_mod) {
    $type = false;
    if (array_key_exists('type', $data)) {
        $type = $data['type'];
    }
    /* Event types: CommitCommentEvent CreateEvent DeleteEvent DeploymentEvent DeploymentStatusEvent DownloadEvent FollowEvent
	 * ForkEvent ForkApplyEvent GistEvent GollumEvent IssueCommentEvent IssuesEvent MemberEvent MembershipEvent PageBuildEvent
	 * PublicEvent PullRequestEvent PullRequestReviewCommentEvent PushEvent ReleaseEvent RepositoryEvent StatusEvent TeamAddEvent
	 * WatchEvent */

    $data = $data['payload'];
    $content = payload_search($data);
    $res = '<div class="msg_text_inner">';
    foreach ($content as $vals) {
        $res .= '<div class="github_para">';
        if (array_key_exists('name', $vals)) {
            $res .= $output_mod->html_safe($vals['name']);
            $res .= '</div><div class="github_para">';
        }
        if (array_key_exists('body', $vals)) {
            $res .= $output_mod->html_safe(wordwrap($vals['body'], 100));
        }
        if (array_key_exists('message', $vals)) {
            $res .= $output_mod->html_safe(wordwrap($vals['message'], 100));
        }
        if (array_key_exists('html_url', $vals)) {
            $res .= sprintf('<div class="github_link"><a href="%s">%s</a></div>',
                $output_mod->html_safe($vals['html_url']), $output_mod->html_safe($vals['html_url']));
        }
        if (array_key_exists('url', $vals) && array_key_exists('sha', $vals)) {
            $url = str_replace(array('commits', 'https://api.github.com/repos'), array('commit', 'https://github.com'), $vals['url']);
            $res .= sprintf('<div class="github_link"><a href="%s">%s</a></div>',
                $output_mod->html_safe($url), $output_mod->html_safe($vals['sha']));
        }
        $res .= '</div>';
    }
    $res .= '</div>';
    return $res;
}}

/**
 * @subpackage github/functions
 */
if (!hm_exists('payload_search')) {
function payload_search($data) {
    $res = array();
    $data_flds = array('url', 'sha', 'body', 'description', 'message', 'name');
    foreach($data as $vals) {
        if (is_array($vals)) {
            $item = array();
            foreach ($data_flds as $fld) {
                if (array_key_exists($fld, $vals)) {
                    $item[$fld] = $vals[$fld];
                }
            }
            if (!empty($item)) {
                if (array_key_exists('html_url', $data)) {
                    $item['html_url'] = $vals['html_url'];
                }
            }
            else {
                $res = array_merge($res, payload_search($vals));
            }
            if (count($item) > 0) {
                $res[] = $item;
            }
        }
    }
    return $res;
}}
