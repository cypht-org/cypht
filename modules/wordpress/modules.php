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
class Hm_Handler_wp_load_sources extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
        }
        else {
            $path = '';
        }
        if ($path == 'combined_inbox') {
            $this->append('data_sources', array('callback' => 'load_wp_notices_for_combined_list', 'type' => 'wordpress', 'name' => 'WordPress.com Notifications', 'id' => 0));
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_get_wp_notice_data extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('uid', $this->request->get)) {
            $wp_details = $this->user_config->get('wp_connect_details', array());
            if ($wp_details) {
                $this->out('wp_notice_details', wp_get_notice_detail($wp_details, $this->request->get['uid']));
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
            if ($path == 'wp_notifications') {
                $this->out('list_path', 'wp_notifications');
                $this->out('list_parent', 'wp_notifications');
                $this->out('mailbox_list_title', array('WordPress.com Notifications'));
                $this->append('data_sources', array('callback' => 'load_wp_notices', 'type' => 'wordpress', 'name' => 'WordPress.com Notifications', 'id' => 0));
            }
            elseif ($path == 'wp_freshly_pressed') {
                $this->out('list_path', 'wp_freshly_pressed');
                $this->out('list_parent', 'wp_freshly_pressed');
                $this->out('mailbox_list_title', array('WordPress.com Freshly Pressed'));
                $this->append('data_sources', array('callback' => 'load_freshly_pressed', 'type' => 'wordpress', 'name' => 'WordPress.com Freshly Pressed', 'id' => 0));
            }
            $list_style = $this->user_config->get('list_style', false);
            if ($this->get('is_mobile', false)) {
                $list_style = 'news_style';
            }
            if ($list_style == 'news_style') {
                $this->out('news_list_style', true);
            }
            $this->out('message_list_fields', array(
                array('chkbox_col', false, false),
                array('source_col', 'source', 'Source'),
                array('from_col', 'from', 'From'),
                array('subject_col', 'subject', 'Subject'),
                array('date_col', 'msg_date', 'Date'),
                array('icon_col', false, false)), false);
        }
    }
}

/**
 * @subpackage wordpress/handler
 */
class Hm_Handler_wp_freshly_pressed_data extends Hm_Handler_Module {
    public function process() {
        $res = array();
        $wp_details = $this->user_config->get('wp_connect_details', array());
        $details = wp_get_freshly_pressed($wp_details);
        if (array_key_exists('posts', $details)) {
            $this->out('wp_freshly_pressed_data', $details['posts']);
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
        //$this->out('list_path', 'wp_notifications');
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
        $res = '<li class="menu_wp_notifications"><a class="unread_link" href="?page=message_list&list_path=wp_notifications">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$env_closed).
            '" alt="" width="16" height="16" /> '.$this->trans('Notifications').'</a></li>';
        $res .= '<li class="menu_wp_freshly_pressed"><a class="unread_link" href="?page=message_list&list_path=wp_freshly_pressed">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$env_closed).
            '" alt="" width="16" height="16" /> '.$this->trans('Freshly Pressed').'</a></li>';
        $this->append('folder_sources', 'wordPress_folders');
        Hm_Page_Cache::add('wordPress_folders', $res, true);
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
class Hm_Output_filter_wp_freshly_pressed_data extends Hm_Output_Module {
    protected function output() {
        $res = array();
        foreach ($this->get('wp_freshly_pressed_data') as $vals) {
            $id = $vals['ID'].'_'.$vals['site_ID'];
            $url = '?page=message&list_path=wp_freshly_pressed&uid='.$this->html_safe($id);
            $style = 'email';
            $style = $this->get('news_list_style') ? 'news' : 'email';
            if ($this->get('is_mobile')) {
                $style = 'news';
            }
            $subject = html_entity_decode($vals['title']);
            $from = $vals['author']['name'];
            $ts = strtotime($vals['date']);
            $date = date('r', $ts);
            if ($style == 'news') {
                $res[$id] = message_list_row(array(
                        array('checkbox_callback', $id),
                        array('icon_callback', array()),
                        array('subject_callback', $subject, $url, array()),
                        array('safe_output_callback', 'source', 'WordPress'),
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
                        array('safe_output_callback', 'source', 'WordPress'),
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
 * @subpackage wordpress/output
 */
class Hm_Output_filter_wp_notification_data extends Hm_Output_Module {
    protected function output() {
        $res = array();
        foreach ($this->get('wp_notice_data', array()) as $vals) {
            if (array_key_exists('id', $vals)) {
                $id = $vals['id'];
                $url = '?page=message&list_path=wp_notifications&uid='.$this->html_safe($id);;
                $style = 'email';
                $style = $this->get('news_list_style') ? 'news' : 'email';
                if ($this->get('is_mobile')) {
                    $style = 'news';
                }
                $subject = html_entity_decode($vals['subject']['text']);
                $from = ucfirst(str_replace('_', ' ', $vals['type']));
                $ts = $vals['timestamp'];
                $flags = array();
                if ($vals['unread'] === "0") {
                    $flags[] = 'unseen';
                }
                $date = date('r', $ts);
                if ($style == 'news') {
                    $res[$id] = message_list_row(array(
                            array('checkbox_callback', $id),
                            array('icon_callback', $flags),
                            array('subject_callback', $subject, $url,$flags),
                            array('safe_output_callback', 'source', 'WordPress'),
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
                            array('safe_output_callback', 'source', 'WordPress'),
                            array('safe_output_callback', 'from', $from),
                            array('subject_callback', $subject, $url, $flags),
                            array('date_callback', human_readable_interval($date), $ts),
                            array('icon_callback', $flags)
                        ),
                        $id,
                        $style,
                        $this
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
class Hm_Output_wordpress_connect_section extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('wp_connect_details', array());
        $res = '<div class="wordpress_connect"><div data-target=".wordpress_connect_section" class="server_section">'.
            '<img src="'.Hm_Image_Sources::$key.'" alt="" width="16" height="16" /> '.
            $this->trans('WordPress.com Connect').'</div><div class="wordpress_connect_section">'.
            'Connect to WordPress.com to view notifications and posts.<br /><br />';
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
function wp_build_notice_headers($data, $output_mod) {
    return '<table class="msg_headers">'.
        '<col class="header_name_col"><col class="header_val_col"></colgroup>'.
        '<tr class="header_subject"><th colspan="2">'.$output_mod->html_safe(html_entity_decode($data['subject']['text'])).
        '</th></tr>'.
        '<tr class="header_date"><th>'.$output_mod->trans('Date').'</th><td>'.date('r', $data['timestamp']).
        ' ('.human_readable_interval(date('r', $data['timestamp'])).')</td></tr>'.
        '<tr class="header_type"><th>'.$output_mod->trans('Type').'</th><td>'.$data['type'].'</td></tr>'.
        '<tr class="header_cid"><th>'.$output_mod->trans('Id').'</th><td>'.$data['id'].'</td></tr></table>';
}

/**
 * @subpackage wordpress/functions
 */
function wp_build_notice_text($type, $data) {
    $res = array();
    if ($type == 'comment') {
        foreach ($data['items'] as $vals) {
            $res[] = $vals['header_text'].'<br />'.$vals['html'];
        }
    }
    return '<div class="msg_text_inner">'.format_msg_html(implode('<div class="hr"></div>', $res)).'</div>';
}

/**
 * @subpackage wordpress/functions
 */
function wp_get_notifications($details) {
    $result = array();
    $url = 'https://public-api.wordpress.com/rest/v1/notifications/?number=20&fields=id,type,unread,subject,timestamp';
    return wp_fetch_content($details, $url);
}

/**
 * @subpackage wordpress/functions
 */
function wp_get_notice_detail($details, $uid) {
    $uid = (int) $uid;
    $url = 'https://public-api.wordpress.com/rest/v1/notifications/'.$uid;
    return wp_fetch_content($details, $url);
}

/**
 * @subpackage wordpress/functions
 */
function wp_get_freshly_pressed($details) {
    $url = 'https://public-api.wordpress.com/rest/v1.1/freshly-pressed/?number=20&fields=ID,site_ID,author,date,title';
    return wp_fetch_content($details, $url);
}

/**
 * @subpackage wordpress/functions
 */
function wp_fetch_content($details, $url) {
    $result = array();
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
