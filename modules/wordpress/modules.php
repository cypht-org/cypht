<?php

/**
 * WordPress modules
 * @package modules
 * @subpackage wordpress
 */

define('WPCOM_READ_URL', 'https://public-api.wordpress.com/rest/v1.1/notifications/read');
define('WPCOM_NOTICES_URL', 'https://public-api.wordpress.com/rest/v1/notifications/?number=20&fields=id,type,unread,subject,timestamp');
define('WPCOM_NOTICE_URL', 'https://public-api.wordpress.com/rest/v1/notifications/');

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_process_wordpress_since_setting extends Hm_Handler_Module {
    public function process() {
        process_site_setting('wordpress_since', $this, 'since_setting_callback');
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_process_unread_wp_included extends Hm_Handler_Module {
    public function process() {
        function unread_wp_setting_callback($val) { return $val; }
        process_site_setting('unread_exclude_wordpress', $this, 'unread_wp_setting_callback', false, true);
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_wordpress_msg_action extends Hm_Handler_Module {
    public function process() {

        list($success, $form) = $this->process_form(array('action_type', 'message_ids'));
        if ($success) {
            $id_list = explode(',', $form['message_ids']);
            $wp_details = $this->user_config->get('wp_connect_details', array());
            $wp_ids = array();
            if ($form['action_type'] == 'read') {
                foreach ($id_list as $msg_id) {
                    if (preg_match("/^wordpress_0_(\d)+$/", $msg_id)) {
                        $parts = explode('_', $msg_id, 3);
                        $wp_ids[] = $parts[2];
                    }
                }
                if (!empty($wp_ids)) {
                    $post = array();
                    foreach ($wp_ids as $id) {
                        $post['counts['.$id.']'] = 5;
                    }
                    $res = wp_fetch_content($wp_details, WPCOM_READ_URL, $post);
                    if (!is_array($res) || !array_key_exists('success', $res) || $res['success'] != 1) {
                        Hm_Msgs::add('ERRUnable to update read status of WordPress notification');
                    }
                }
            }
        }
    }
}

/**
 * @subpackage wordpress/handler
 * @todo: fix for background unread 
 */
class Hm_Handler_wp_load_sources extends Hm_Handler_Module {
    public function process() {
        $wp_details = $this->user_config->get('wp_connect_details', array());
        if (empty($wp_details)) {
            return;
        }
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
        }
        else {
            $path = '';
        }
        if ($path == 'combined_inbox' || $path == 'unread') {
            $excluded = false;
            if ($path == 'unread' && $this->user_config->get('unread_exclude_wordpress_setting', false)) {
                $excluded = true;
            }
            if (!$excluded) {
                $this->append('data_sources', array('callback' => 'load_wp_notices_for_combined_list', 'type' => 'wordpress', 'name' => 'WordPress.com Notifications', 'id' => 0));
            }
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_wordpress_folders_data extends Hm_Handler_Module {
    public function process() {
        $this->out('wp_connect_details', $this->user_config->get('wp_connect_details', array()));
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_get_wp_notice_data extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('wp_uid', $this->request->post)) {
            $wp_details = $this->user_config->get('wp_connect_details', array());
            if ($wp_details) {
                $parts = explode('_', $this->request->post['wp_uid'], 3);
                $wp_id = $parts[2];
                $details = wp_get_notice_detail($wp_details, $wp_id);
                if (is_array($details) && !empty($details)) {
                    if (preg_match("/^wordpress_0_(\d)+$/", $this->request->post['wp_uid'])) {
                        $post = array('counts['.$wp_id.']' => 5);
                        $res = wp_fetch_content($wp_details, WPCOM_READ_URL, $post);
                        if (!is_array($res) || !array_key_exists('success', $res) || $res['success'] != 1) {
                            Hm_Msgs::add('ERRUnable to update read status of WordPress notification');
                        }
                    }
                    $this->out('wp_notice_details', $details);
                }
            }
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_wordpress_list_type extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            $parent = '';
            if (array_key_exists('list_parent', $this->request->get)) {
                $parent = $this->request->get['list_parent'];
            }
            elseif (in_array($path, array('combined_inbox', 'unread'), true)) {
                $parent = $path;
            }
            if ($path == 'wp_notifications') {
                $this->out('list_path', 'wp_notifications', false);
                $this->out('list_parent', $parent);
                $this->out('mailbox_list_title', array('WordPress.com Notifications'));
                $this->out('message_list_since', $this->user_config->get('wordpress_since_setting', DEFAULT_SINCE));
                $this->out('per_source_limit', 100);
                $this->append('data_sources', array('callback' => 'load_wp_notices', 'type' => 'wordpress', 'name' => 'WordPress.com Notifications', 'id' => 0));
            }
            else {
                $this->out('list_path', $path, false);
                $this->out('list_parent', $parent);
            }
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_wp_notification_data extends Hm_Handler_Module {
    public function process() {
        $res = array();
        $wp_details = $this->user_config->get('wp_connect_details', array());
        $details = wp_get_notifications($wp_details);
        if (array_key_exists('notes', $details)) {
            $res = $details['notes'];
        }
        $this->out('wp_notice_data', $res);
        if (array_key_exists('list_path', $this->request->get) && $this->request->get['list_path'] == 'unread') {
            $this->out('wp_list_since', process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE)));
        }
        elseif (array_key_exists('list_path', $this->request->get) && $this->request->get['list_path'] == 'combined_inbox') {
            $this->out('wp_list_since', process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE)));
        }
        elseif (array_key_exists('list_path', $this->request->get) && $this->request->get['list_path'] == 'wp_notifications') {
            $this->out('wp_list_since', process_since_argument($this->user_config->get('wordpress_since_setting', DEFAULT_SINCE)));
        }
    }
}

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
            $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(serialize($msgs)));
            Hm_Dispatch::page_redirect('?page=servers');
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_wordpress_disconnect extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('wp_disconnect', $this->request->post)) {
            $this->user_config->set('wp_connect_details', array());
            $user_data = $this->user_config->dump();
            if (!empty($user_data)) {
                $this->session->set('user_data', $user_data);
            }
            $this->out('reload_folders', true, false);
            $this->session->record_unsaved('WordPress connection deleted');
            Hm_Msgs::add('WordPress connection deleted');
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_setup_wordpress_connect extends Hm_Handler_Module {
    public function process() {
        $details = wp_connect_details($this->config);
        if (!empty($details)) {
            $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
            $this->out('wp_auth_url', $oauth2->request_authorization_url($details['auth_url'], 'global', 'wp_authorization'));
            $this->out('wp_connect_details', $this->user_config->get('wp_connect_details', array()));
        }
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_wordpress_folders extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('wp_connect_details', array());
        if (!empty($details)) {
            $res = '<li class="menu_wp_notifications"><a class="unread_link" href="?page=message_list&list_path=wp_notifications">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$w).'" alt="" width="16" height="16" /> ';
            }
            $res .= $this->trans('Notifications').'</a></li>';
            $this->append('folder_sources', array('wordPress_folders', $res));
        }
        return '';
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_filter_wp_notice_data extends Hm_Output_Module {
    protected function output() {
        $data = $this->get('wp_notice_details', array());
        if (isset($data['notes'][0]['id'])) {
            $data = $data['notes'][0];
            $this->out('wp_notice_text', wp_build_notice_text($data['type'], $data['body']));
            $this->out('wp_notice_headers', wp_build_notice_headers($data, $this));
        }
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_filter_wp_notification_data extends Hm_Output_Module {
    protected function output() {
        $res = array();
        $unread_only = false;
        if ($this->get('list_path', '') == 'unread') {
            $unread_only = true;
        }
        $cutoff = $this->get('wp_list_since', '');
        if ($cutoff) {
            $cutoff = strtotime($cutoff);
        }
        else {
            $cutoff = 0;
        }
        $show_icons = $this->get('msg_list_icons');
        foreach ($this->get('wp_notice_data', array()) as $vals) {
            $row_class = 'wordpress notifications';
            if (array_key_exists('id', $vals)) {
                $id = 'wordpress_0_'.$vals['id'];
                $url = '?page=message&list_path=wp_notifications&uid='.$this->html_safe($id);;
                if ($this->get('list_parent', '')) {
                    $url .= '&list_parent='.$this->html_safe($this->get('list_parent', ''));
                }
                $style = 'email';
                $style = $this->get('news_list_style') ? 'news' : 'email';
                if ($this->get('is_mobile')) {
                    $style = 'news';
                }
                $subject = html_entity_decode($vals['subject']['text']);
                if (!$subject) {
                    $subject = '[No subject]';
                }
                $from = ucfirst(str_replace('_', ' ', $vals['type']));
                $ts = intval($vals['timestamp']);
                if ($ts < $cutoff) {
                    continue;
                }
                $flags = array();
                if ((int) $vals['unread'] > 0) {
                    $row_class .= ' unseen';
                    $flags[] = 'unseen';
                }
                if ($unread_only && !in_array('unseen', $flags, true)) {
                    continue;
                }
                $icon = '';
                $date = date('r', $ts);
                if ($show_icons) {
                    $icon = 'w';
                }
                if ($style == 'news') {
                    $res[$id] = message_list_row(array(
                            array('checkbox_callback', $id),
                            array('icon_callback', $flags),
                            array('subject_callback', $subject, $url, $flags, $icon),
                            array('safe_output_callback', 'source', 'WordPress'),
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
                            array('safe_output_callback', 'source', 'WordPress', $icon),
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
        }
        $this->out('formatted_message_list', $res);
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_unread_wp_included_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('unread_exclude_wordpress', $settings) && $settings['unread_exclude_wordpress']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        return '<tr class="unread_setting"><td><label for="unread_exclude_wordpress">'.$this->trans('Exclude unread WordPress notices').'</label></td>'.
            '<td><input type="checkbox" '.$checked.' value="1" id="unread_exclude_wordpress" name="unread_exclude_wordpress" /></td></tr>';
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_start_wordpress_settings extends Hm_Output_Module {
    protected function output() {
        return '<tr><td colspan="2" data-target=".wp_notifications_setting" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$w.'" width="16" height="16" />'.$this->trans('WordPress.com Settings').'</td></tr>';
    }
}

/**
 * @subpackage wordpress/output
 */
class Hm_Output_wordpress_since_setting extends Hm_Output_Module {
    protected function output() {
        $since = DEFAULT_SINCE;
        $settings = $this->get('user_settings');
        if (array_key_exists('wordpress_since', $settings) && $settings['wordpress_since']) {
            $since = $settings['wordpress_since'];
        }
        return '<tr class="wp_notifications_setting"><td><label for="wordpress_since">'.$this->trans('Show WordPress.com notices received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'wordpress_since', $this).'</td></tr>';
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
            $this->trans('WordPress.com Connect').'</div><div class="wordpress_connect_section">';
        if (empty($details)) {
            $res .= 'Connect to WordPress.com to view notifications and posts.<br /><br />';
            $res .= '<a href="'.$this->get('wp_auth_url', '').'">'.$this->trans('Enable').'</a></div></div>';
        }
        else {
            $res .= $this->trans('Already connected');
            $res .= '<br /><form id="wp_disconnect_form" method="POST">';
            $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
            $res .= '<input type="submit" name="wp_disconnect" class="wp_disconnect" value="'.$this->trans('Disconnect').'" />';
            $res .= '</form></div></div>';
        }
        return $res;
    }
}

/**
 * @subpackage wordpress/functions
 */
if (!hm_exists('wp_connect_details')) {
function wp_connect_details($config) {
    return get_ini($config, 'wordpress.ini');
}}

/**
 * @subpackage wordpress/functions
 */
if (!hm_exists('wp_build_notice_headers')) {
function wp_build_notice_headers($data, $output_mod) {
    $subject = $data['subject']['text'];
    if (!$subject) {
        $subject = '[No subject]';
    }
    return '<table class="msg_headers">'.
        '<col class="header_name_col"><col class="header_val_col"></colgroup>'.
        '<tr class="header_subject"><th colspan="2">'.$output_mod->html_safe(html_entity_decode($data['subject']['text'])).
        '</th></tr>'.
        '<tr class="header_date"><th>'.$output_mod->trans('Date').'</th><td>'.date('r', $data['timestamp']).
        ' ('.human_readable_interval(date('r', $data['timestamp'])).')</td></tr>'.
        '<tr class="header_type"><th>'.$output_mod->trans('Type').'</th><td>'.$data['type'].'</td></tr>'.
        '<tr class="header_cid"><th>'.$output_mod->trans('Id').'</th><td>'.$data['id'].'</td></tr><tr><td></td><td></td></tr></table>';
}}

/**
 * @subpackage wordpress/functions
 */
if (!hm_exists('wp_build_notice_text')) {
function wp_build_notice_text($type, $data) {
    $res = array();
    if ($type == 'comment') {
        foreach ($data['items'] as $vals) {
            $res[] = $vals['header_text'].'<br />'.$vals['html'];
        }
    }
    return '<div class="msg_text_inner">'.format_msg_html(implode('<div class="hr"></div>', $res)).'</div>';
}}

/**
 * @subpackage wordpress/functions
 */
if (!hm_exists('wp_get_notifications')) {
function wp_get_notifications($details) {
    $result = array();
    return wp_fetch_content($details, WPCOM_NOTICES_URL);
}}

/**
 * @subpackage wordpress/functions
 */
if (!hm_exists('wp_get_notice_detail')) {
function wp_get_notice_detail($details, $uid) {
    $uid = (int) $uid;
    return wp_fetch_content($details, WPCOM_NOTICE_URL.$uid);
}}

/**
 * @subpackage wordpress/functions
 */
if (!hm_exists('wp_fetch_content')) {
function wp_fetch_content($details, $url, $post=array()) {
    if (!is_array($details) || empty($details) || !array_key_exists('access_token', $details)) {
        return array();
    }
    $api = new Hm_API_Curl();
    return $api->command($url, array('Authorization: Bearer ' . $details['access_token']), $post);
}}

