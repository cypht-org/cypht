<?php

if (!defined('DEBUG_MODE')) { die(); }

require 'modules/core/functions.php';

/* INPUT */

class Hm_Handler_process_search_terms extends Hm_Handler_Module {
    public function process($data) {
        if (array_key_exists('search_terms', $this->request->get)) {
            $this->session->set('search_terms', validate_search_terms($this->request->get['search_terms']));
        }
        if (array_key_exists('search_since', $this->request->get)) {
            $this->session->set('search_since', process_since_argument($this->request->get['search_since'], true));
        }
        if (array_key_exists('search_fld', $this->request->get)) {
            $this->session->set('search_fld', validate_search_fld($this->request->get['search_fld']));
        }
        $data['search_since'] = $this->session->get('search_since', DEFAULT_SINCE);
        $data['search_terms'] = $this->session->get('search_terms', '');
        $data['search_fld'] = $this->session->get('search_fld', 'TEXT');
        return $data;
    }
}

class Hm_Handler_http_headers extends Hm_Handler_Module {
    public function process($data) {
        if (array_key_exists('language', $data)) {
            $data['http_headers'][] = 'Content-Language: '.substr($data['language'], 0, 2);
        }
        if ($this->request->tls) {
            $data['http_headers'][] = 'Strict-Transport-Security: max-age=31536000';
        }
        return $data;
    }
}

class Hm_Handler_process_list_style_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'list_style'));
        if ($success) {
            if (in_array($form['list_style'], array('email_style', 'news_style'))) {
                $data['new_user_settings']['list_style'] = $form['list_style'];
            }
            else {
                $data['user_settings']['list_style'] = $this->user_config->get('list_style', false);
            }
        }
        else {
            $data['user_settings']['list_style'] = $this->user_config->get('list_style', false);
        }
        return $data;
    }
}

class Hm_Handler_process_change_password extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('new_pass1', 'new_pass2'));
        if ($success) {
            if ($this->session->internal_users) {
                if ($form['new_pass1'] && $form['new_pass2']) {
                    if ($form['new_pass1'] != $form['new_pass2']) {
                        Hm_Msgs::add("ERRNew passwords don't match");
                    }
                    else {
                        $user = $this->session->get('username', false);
                        if ($this->session->change_pass($user, $form['new_pass1'])) {
                            $data['new_password'] = $form['new_pass1'];
                        }
                    }
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_process_unread_source_max_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'unread_per_source'));
        if ($success) {
            if ($form['unread_per_source'] > MAX_PER_SOURCE || $form['unread_per_source'] < 0) {
                $sources = DEFAULT_PER_SOURCE;
            }
            else {
                $sources = $form['unread_per_source'];
            }
            $data['new_user_settings']['unread_per_source_setting'] = $sources;
        }
        else {
            $data['user_settings']['unread_per_source'] = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
        }
        return $data;
    }
}

class Hm_Handler_process_all_source_max_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'all_per_source'));
        if ($success) {
            if ($form['all_per_source'] > MAX_PER_SOURCE || $form['all_per_source'] < 0) {
                $sources = DEFAULT_PER_SOURCE;
            }
            else {
                $sources = $form['all_per_source'];
            }
            $data['new_user_settings']['all_per_source_setting'] = $sources;
        }
        else {
            $data['user_settings']['all_per_source'] = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
        }
        return $data;
    }
}

class Hm_Handler_process_flagged_source_max_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'flagged_per_source'));
        if ($success) {
            if ($form['flagged_per_source'] > MAX_PER_SOURCE || $form['flagged_per_source'] < 0) {
                $sources = DEFAULT_PER_SOURCE;
            }
            else {
                $sources = $form['flagged_per_source'];
            }
            $data['new_user_settings']['flagged_per_source_setting'] = $sources;
        }
        else {
            $data['user_settings']['flagged_per_source'] = $this->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
        }
        return $data;
    }
}

class Hm_Handler_process_flagged_since_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'flagged_since'));
        if ($success) {
            $data['new_user_settings']['flagged_since_setting'] = process_since_argument($form['flagged_since'], true);
        }
        else {
            $data['user_settings']['flagged_since'] = $this->user_config->get('flagged_since_setting', false);
        }
        return $data;
    }
}

class Hm_Handler_process_all_since_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'all_since'));
        if ($success) {
            $data['new_user_settings']['all_since_setting'] = process_since_argument($form['all_since'], true);
        }
        else {
            $data['user_settings']['all_since'] = $this->user_config->get('all_since_setting', false);
        }
        return $data;
    }
}

class Hm_Handler_process_unread_since_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'unread_since'));
        if ($success) {
            $data['new_user_settings']['unread_since_setting'] = process_since_argument($form['unread_since'], true);
        }
        else {
            $data['user_settings']['unread_since'] = $this->user_config->get('unread_since_setting', false);
        }
        return $data;
    }
}

class Hm_Handler_process_language_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'language_setting'));
        if ($success) {
            $data['new_user_settings']['language_setting'] = $form['language_setting'];
        }
        else {
            $data['user_settings']['language'] = $this->user_config->get('language_setting', false);
        }
        return $data;
    }
}

class Hm_Handler_process_timezone_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'timezone_setting'));
        if ($success) {
            $data['new_user_settings']['timezone_setting'] = $form['timezone_setting'];
        }
        else {
            $data['user_settings']['timezone'] = $this->user_config->get('timezone_setting', false);
        }
        return $data;
    }
}

class Hm_Handler_save_user_settings extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'password'));
        if ($success) {
            if (array_key_exists('new_user_settings', $data)) {
                foreach ($data['new_user_settings'] as $name => $value) {
                    $this->user_config->set($name, $value);
                }
                $user = $this->session->get('username', false);
                $path = $this->config->get('user_settings_dir', false);

                if (array_key_exists('new_password', $data)) {
                    $pass = $data['new_password'];
                }
                elseif ($this->session->auth($user, $form['password'])) {
                    $pass = $form['password'];
                }
                else {
                    Hm_Msgs::add('ERRIncorrect password, could not save settings to the server');
                    /* TODO: save current settings in session */
                    $pass = false;
                }
                if ($user && $path && $pass) {
                    $this->user_config->save($user, $pass);
                    Hm_Msgs::add('Settings saved');
                    $data['reload_folders'] = true;
                }
                Hm_Page_Cache::flush($this->session);
            }
        }
        elseif (array_key_exists('save_settings', $this->request->post)) {
            /* TODO: save current settings in session */
            Hm_Msgs::add('ERRYour password is required to save your settings to the server');
        }
        return $data;
    }
}

class Hm_Handler_title extends Hm_Handler_Module {
    public function process($data) {
        $data['title'] = ucfirst($this->page);
        return $data;
    }
}

class Hm_Handler_language extends Hm_Handler_Module {
    public function process($data) {
        $data['language'] = $this->user_config->get('language_setting', 'en_US');
        //$data['language'] = $this->session->get('language', 'en_US');
        return $data;
    }
}

class Hm_Handler_date extends Hm_Handler_Module {
    public function process($data) {
        $data['date'] = date('G:i:s');
        return $data;
    }
}

class Hm_Handler_login extends Hm_Handler_Module {
    public function process($data) {
        if (!array_key_exists('create_hm_user', $this->request->post)) {
            list($success, $form) = $this->process_form(array('username', 'password'));
            if ($success) {
                $this->session->check($this->request, $form['username'], $form['password']);
                $this->session->set('username', $form['username']);
            }
            else {
                $this->session->check($this->request);
            }
            $data['internal_users'] = $this->session->internal_users;
            if ($this->session->is_active()) {
                Hm_Page_Cache::load($this->session);
            }
        }
        return $data;
    }
}

class Hm_Handler_create_user extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('username', 'password', 'create_hm_user'));
        if ($success) {
            if ($this->session->internal_users) {
                $this->session->create($this->request, $form['username'], $form['password']);
            }
        }
        return $data;
    }
}

class Hm_Handler_load_user_data extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('username', 'password'));
        if ($this->session->is_active()) {
            if ($success) {
                $this->user_config->load($form['username'], $form['password']);
            }
            else {
                $user_data = $this->session->get('user_data', array());
                if (!empty($user_data)) {
                    $this->user_config->reload($user_data);
                }
                $pages = $this->user_config->get('saved_pages', array());
                if (!empty($pages)) {
                    $this->session->set('saved_pages', $pages);
                }
            }
        }
        $data['is_mobile'] = $this->request->mobile;
        return $data;
    }
}

class Hm_Handler_save_user_data extends Hm_Handler_Module {
    public function process($data) {
        $user_data = $this->user_config->dump();
        if (!empty($user_data)) {
            $this->session->set('user_data', $user_data);
        }
        return $data;
    }
}

class Hm_Handler_logout extends Hm_Handler_Module {
    public function process($data) {
        if (array_key_exists('logout', $this->request->post) && !$this->session->loaded) {
            $this->session->destroy($this->request);
            Hm_Msgs::add('Session destroyed on logout');
        }
        elseif (array_key_exists('save_and_logout', $this->request->post)) {
            list($success, $form) = $this->process_form(array('password'));
            if ($success) {
                $user = $this->session->get('username', false);
                $path = $this->config->get('user_settings_dir', false);
                $pages = $this->session->get('saved_pages', array());
                if (!empty($pages)) {
                    $this->user_config->set('saved_pages', $pages);
                }
                if ($this->session->auth($user, $form['password'])) {
                    $pass = $form['password'];
                }
                else {
                    Hm_Msgs::add('ERRIncorrect password, could not save settings to the server');
                    $pass = false;
                }
                if ($user && $path && $pass) {
                    $this->user_config->save($user, $pass);
                    $this->session->destroy($this->request);
                    Hm_Msgs::add('Saved user data on logout');
                    Hm_Msgs::add('Session destroyed on logout');
                }
            }
            else {
                Hm_Msgs::add('ERRYour password is required to save your settings to the server');
            }
        }
        return $data;
    }
}

class Hm_Handler_message_list_type extends Hm_Handler_Module {
    public function process($data) {
        $data['list_path'] = false;
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if ($path == 'unread') {
                $data['list_path'] = 'unread';
                $data['mailbox_list_title'] = array('Unread');
                $data['message_list_since'] = $this->user_config->get('unread_since_setting', DEFAULT_SINCE);
                $data['per_source_limit'] = $this->user_config->get('unread_per_source_setting', DEFAULT_SINCE);
            }
            elseif ($path == 'email') {
                $data['list_path'] = 'email';
                $data['mailbox_list_title'] = array('All Email');
            }
            elseif ($path == 'feeds') {
                $data['list_path'] = 'feeds';
                $data['mailbox_list_title'] = array('All Feeds');
                $data['message_list_since'] = $this->user_config->get('feed_since', DEFAULT_SINCE);
                $data['per_source_limit'] = $this->user_config->get('feed_limit', DEFAULT_SINCE);
            }
            elseif ($path == 'flagged') {
                $data['list_path'] = 'flagged';
                $data['message_list_since'] = $this->user_config->get('flagged_since_setting', DEFAULT_SINCE);
                $data['per_source_limit'] = $this->user_config->get('flagged_per_source_setting', DEFAULT_SINCE);
                $data['mailbox_list_title'] = array('Flagged');
            }
            elseif ($path == 'combined_inbox') {
                $data['list_path'] = 'combined_inbox';
                $data['message_list_since'] = $this->user_config->get('all_since_setting', DEFAULT_SINCE);
                $data['per_source_limit'] = $this->user_config->get('all_per_source_setting', DEFAULT_SINCE);
                $data['mailbox_list_title'] = array('Everything');
            }
            elseif (preg_match("/^feeds_\d+$/", $path)) {
                $data['message_list_since'] = $this->user_config->get('feed_since', DEFAULT_SINCE);
                $data['per_source_limit'] = $this->user_config->get('feed_limit', DEFAULT_SINCE);
                $data['list_path'] = $path;
                $parts = explode('_', $path, 2);
                $details = Hm_Feed_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    $data['mailbox_list_title'] = array('Feeds', $details['name']);
                }
            }
        }
        if (array_key_exists('list_parent', $this->request->get)) {
            $data['list_parent'] = $this->request->get['list_parent'];
        }
        else {
            $data['list_parent'] = false;
        }
        if (array_key_exists('list_page', $this->request->get)) {
            $data['list_page'] = (int) $this->request->get['list_page'];
            if ($data['list_page'] < 1) {
                $data['list_page'] = 1;
            }
        }
        else {
            $data['list_page'] = 1;
        }
        if (array_key_exists('uid', $this->request->get) && preg_match("/\d+/", $this->request->get['uid'])) {
            $data['uid'] = $this->request->get['uid'];
        }
        $list_style = $this->user_config->get('list_style', false);
        if ($data['is_mobile']) {
            $list_style = 'news_style';
        }
        if ($list_style == 'news_style') {
            $data['no_message_list_headers'] = true;
            $data['news_list_style'] = true;
        }
        return $data;
    }
}

class Hm_Handler_reload_folder_cookie extends Hm_Handler_Module {
    public function process($data) {
        if (array_key_exists('reload_folders', $data)) {
            secure_cookie($this->request, 'hm_reload_folders', '1');
        }
    }
}


/* OUTPUT */

class Hm_Output_login extends Hm_Output_Module {
    protected function output($input, $format) {
        if (!$input['router_login_state']) {
            $res = '<form class="login_form" method="POST">'.
                '<h1 class="title">HM3</h1>'.
                ' <input type="text" placeholder="'.$this->trans('Username').'" name="username" value="">'.
                ' <input type="password" placeholder="'.$this->trans('Password').'" name="password">'.
                ' <input type="submit" value="Login" />';
            if (array_key_exists('internal_users', $input) && $input['internal_users'] && $input['router_page_name'] == 'home') {
                $res .= ' <input type="submit" name="create_hm_user" value="Create" />';
            }
            $res .= '</form>';
            return $res;
        }
        return '';
    }
}

class Hm_Output_date extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="date">'.$this->html_safe($input['date']).'</div>';
    }
}

class Hm_Output_msgs extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        $msgs = Hm_Msgs::get();
        $res .= '<div class="sys_messages">';
        if (!empty($msgs)) {
            $res .= implode(',', array_map(function($v) {
                if (preg_match("/ERR/", $v)) {
                    return sprintf('<span class="err">%s</span>', substr($this->html_safe($v), 3));
                }
                else {
                    return $this->html_safe($v);
                }
            }, $msgs));
        }
        $res .= '</div>';
        return $res;
    }
}

class Hm_Output_header_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $lang = '';
        if ($this->lang) {
            $lang = 'lang='.strtolower(str_replace('_', '-', $this->lang));
        }
        return '<!DOCTYPE html><html '.$lang.'><head>';
    }
}

class Hm_Output_header_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</head>';
    }
}

class Hm_Output_content_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<body';
        if (!$input['router_login_state']) {
            $res .= '><script type="text/javascript">sessionStorage.clear();</script>';
        }
        else {
            $res .= ' style=""><noscript class="noscript">You Need to have Javascript enabled to use HM3. Sorry about that!</noscript>';
        }
        return $res;
    }
}

class Hm_Output_header_content extends Hm_Output_Module {
    protected function output($input, $format) {
        $title = 'HM3';
        if (array_key_exists('mailbox_list_title', $input)) {
            $title .= ' '.implode('-', array_slice($input['mailbox_list_title'], 1));
        }
        elseif (array_key_exists('router_page_name', $input)) {
            if (array_key_exists('list_path', $input) && $input['router_page_name'] == 'message_list') {
                $title .= ' '.ucwords(str_replace('_', ' ', $input['list_path']));
            }
            elseif ($input['router_page_name'] == 'notfound') {
                $title .= ' Nope';
            }
            else {
                $title .= ' '.ucfirst($input['router_page_name']);
            }
        }
        return '<title>'.$this->html_safe($title).'</title><meta charset="utf-8" />'.
            '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">'.
            '<link rel="icon" class="tab_icon" type="image/png" href="'.Hm_Image_Sources::$env_closed.'">'.
            '<base href="'.$this->html_safe($input['router_url_path']).'" />';
    }
}

class Hm_Output_header_css extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (DEBUG_MODE) {
            foreach (glob('modules/*', GLOB_ONLYDIR | GLOB_MARK) as $name) {
                if (is_readable(sprintf("%ssite.css", $name))) {
                    $res .= '<link href="'.sprintf("%ssite.css", $name).'" media="all" rel="stylesheet" type="text/css" />';
                }
            }
        }
        else {
            $res .= '<link href="site.css" media="all" rel="stylesheet" type="text/css" />';
        }
        return $res;
    }
}

class Hm_Output_page_js extends Hm_Output_Module {
    protected function output($input, $format) {
        if (DEBUG_MODE) {
            $res = '';
            foreach (glob('modules/*', GLOB_ONLYDIR | GLOB_MARK) as $name) {
                if (is_readable(sprintf("%ssite.js", $name))) {
                    $res .= '<script type="text/javascript" src="'.sprintf("%ssite.js", $name).'"></script>';
                }
            }
            return $res;
        }
        else {
            return '<script type="text/javascript" src="site.js"></script>';
        }
    }
}

class Hm_Output_content_end extends Hm_Output_Module {
    protected function output($input, $format) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            return '<div class="debug"></div></body></html>';
        }
        else {
            return '</body></html>';
        }
    }
}

class Hm_Output_zepto extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<script type="text/javascript" src="modules/core/zepto.min.js"></script>';
    }
}

class Hm_Output_js_data extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<script type="text/javascript">'.
            'var hm_url_path = "'.$this->html_safe($input['router_url_path']).'";'.
            'var hm_page_name = "'.$this->html_safe($input['router_page_name']).'";'.
            'var hm_list_path = "'.(array_key_exists('list_path', $input) ? $this->html_safe($input['list_path']) : '').'";'.
            'var hm_list_parent = "'.(array_key_exists('list_parent', $input) ? $this->html_safe($input['list_parent']) : '').'";'.
            'var hm_msg_uid = "'.(array_key_exists('uid', $input) ? $this->html_safe($input['uid']) : 0).'";'.
            'var hm_module_list = "'.$this->html_safe($input['router_module_list']).'";'.
            'var hm_search_terms = "'.(array_key_exists('search_terms', $input) ? $this->html_safe($input['search_terms']) : '').'";'.
            'var hm_search_fld = "'.(array_key_exists('search_fld', $input) ? $this->html_safe($input['search_fld']) : '').'";'.
            'var hm_search_since = "'.(array_key_exists('search_since', $input) ? $this->html_safe($input['search_since']) : '').'";'.
            '</script>';
    }
}

class Hm_Output_loading_icon extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="loading_icon"><img alt="Loading..." src="'.Hm_Image_Sources::$loading.'" width="67" height="10" /></div>';
    }
}

class Hm_Output_start_settings_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="user_settings"><div class="content_title">Site Settings</div><br />'.
            '<form method="POST"><table class="settings_table"><colgroup>'.
            '<col class="label_col"><col class="setting_col"></colgroup>';
    }
}

class Hm_Output_list_style_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $options = array('email_style' => 'Email', 'news_style' => 'News');

        if (array_key_exists('user_settings', $input) && array_key_exists('list_style', $input['user_settings'])) {
            $list_style = $input['user_settings']['list_style'];
        }
        else {
            $list_style = false;
        }
        $res = '<tr><td>Message list style</td><td><select name="list_style">';
        foreach ($options as $val => $label) {
            $res .= '<option ';
            if ($list_style == $val) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$val.'">'.$label.'</option>';
        }
        $res .= '</select></td></tr>';
        return $res;
    }
}

class Hm_Output_change_password extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (array_key_exists('internal_users', $input) && $input['internal_users']) {
            $res .= '<tr><td>Change password</td><td><input type="password" name="new_pass1" placeholder="New password" />'.
                ' <input type="password" name="new_pass2" placeholder="New password again" /></td></tr>';
        }
        return $res;
    }
}

class Hm_Output_start_flagged_settings extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td colspan="2" class="settings_subtitle">'.
            '<br /><img alt="" src="'.Hm_Image_Sources::$star.'" width="16" height="16" />'.
            $this->trans('Flagged Page').'</td></tr>';
    }
}

class Hm_Output_start_everything_settings extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td colspan="2" class="settings_subtitle">'.
            '<br /><img alt="" src="'.Hm_Image_Sources::$box.'" width="16" height="16" />'.
            $this->trans('Everything Page').'</td></tr>';
    }
}

class Hm_Output_start_unread_settings extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td colspan="2" class="settings_subtitle">'.
            '<br /><img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            $this->trans('Unread Page').'</td></tr>';
    }
}

class Hm_Output_start_general_settings extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$cog.'" width="16" height="16" />'.
            $this->trans('General').'</td></tr>';
    }
}

class Hm_Output_unread_source_max_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $sources = DEFAULT_PER_SOURCE;
        if (array_key_exists('user_settings', $input) && array_key_exists('unread_per_source', $input['user_settings'])) {
            $sources = $input['user_settings']['unread_per_source'];
        }
        return '<tr><td>Max messages per source</td><td><input type="text" size="2" name="unread_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

class Hm_Output_unread_since_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $since = false;
        if (array_key_exists('user_settings', $input) && array_key_exists('unread_since', $input['user_settings'])) {
            $since = $input['user_settings']['unread_since'];
        }
        return '<tr><td>Show messages received since</td><td>'.message_since_dropdown($since, 'unread_since').'</td></tr>';
    }
}

class Hm_Output_flagged_source_max_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $sources = DEFAULT_PER_SOURCE;
        if (array_key_exists('user_settings', $input) && array_key_exists('flagged_per_source', $input['user_settings'])) {
            $sources = $input['user_settings']['flagged_per_source'];
        }
        return '<tr><td>Max messages per source</td><td><input type="text" size="2" name="flagged_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

class Hm_Output_flagged_since_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $since = false;
        if (array_key_exists('user_settings', $input) && array_key_exists('flagged_since', $input['user_settings'])) {
            $since = $input['user_settings']['flagged_since'];
        }
        return '<tr><td>Show messages received since</td><td>'.message_since_dropdown($since, 'flagged_since').'</td></tr>';
    }
}

class Hm_Output_all_source_max_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $sources = DEFAULT_PER_SOURCE;
        if (array_key_exists('user_settings', $input) && array_key_exists('all_per_source', $input['user_settings'])) {
            $sources = $input['user_settings']['all_per_source'];
        }
        return '<tr><td>Max messages per source</td><td><input type="text" size="2" name="all_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

class Hm_Output_all_since_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $since = false;
        if (array_key_exists('user_settings', $input) && array_key_exists('all_since', $input['user_settings'])) {
            $since = $input['user_settings']['all_since'];
        }
        return '<tr><td>Show messages received since</td><td>'.message_since_dropdown($since, 'all_since').'</td></tr>';
    }
}

class Hm_Output_language_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $langs = array(
            'en_US' => 'English',
            'es_ES' => 'Spanish'
        );
        if (array_key_exists('user_settings', $input) && array_key_exists('language', $input['user_settings'])) {
            $mylang = $input['user_settings']['language'];
        }
        else {
            $mylang = false;
        }
        $res = '<tr><td>Interface language</td><td><select name="language_setting">';
        foreach ($langs as $id => $lang) {
            $res .= '<option ';
            if ($id == $mylang) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$id.'">'.$lang.'</option>';
        }
        $res .= '</select></td></tr>';
        return $res;
    }
}

class Hm_Output_timezone_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $zones = timezone_identifiers_list();
        if (array_key_exists('user_settings', $input) && array_key_exists('timezone', $input['user_settings'])) {
            $myzone = $input['user_settings']['timezone'];
        }
        else {
            $myzone = false;
        }
        $res = '<tr><td>Timezone</td><td><select name="timezone_setting">';
        foreach ($zones as $zone) {
            $res .= '<option ';
            if ($zone == $myzone) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$zone.'">'.$zone.'</option>';
        }
        $res .= '</select></td></tr>';
        return $res;
    }
}

class Hm_Output_end_settings_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td class="submit_cell" colspan="2">'.
            '<input name="password" class="save_settings_password" type="password" placeholder="Password" />'.
            '<input class="save_settings" type="submit" name="save_settings" value="Save" />'.
            '<div class="password_notice">* You must enter your password to save your settings on the server</div>'.
            '</td></tr></table></form></div>';
    }
}

class Hm_Output_two_col_layout_start extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="framework">';
    }
}

class Hm_Output_two_col_layout_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</div><br class="end_float" />';
    }
}

class Hm_Output_folder_list_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<a class="folder_toggle" href="#" onclick="return open_folder_list();"><img alt="" src="'.Hm_Image_Sources::$big_caret.'" width="20" height="20" /></a>'.
            '<div class="folder_cell"><div class="folder_list">';
        return $res;
    }
}

class Hm_Output_folder_list_content extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = main_menu($input, $this);
        $res .= folder_source_menu($input, $this);
        $res .= settings_menu($input, $this);
        $res .= '<a href="#" onclick="return update_folder_list();" class="update_message_list">[reload]</a>';
        $res .= '<a href="#" onclick="return hide_folder_list();" class="hide_folders"><img src="'.Hm_Image_Sources::$big_caret_left.'" alt="Collapse" width="16" height="16" /></a>';
        if ($format == 'HTML5') {
            return $res;
        }
        $input['formatted_folder_list'] = $res;
        return $input;
    }
}

class Hm_Output_folder_list_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</div></div>';
    }
}
class Hm_Output_content_section_start extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="content_cell">';
    }
}

class Hm_Output_content_section_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</div>';
    }
}

class Hm_Output_server_status_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<div class="server_status"><div class="content_title">Home</div>';
        $res .= '<table><thead><tr><th>Type</th><th>Name</th><th>Status</th><th>Details</th></tr>'.
                '</thead><tbody>';
        return $res;
    }
}

class Hm_Output_server_status_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</tbody></table></div>';
    }
}

class Hm_Output_message_start extends Hm_Output_Module {
    protected function output($input, $format) {
        if (array_key_exists('list_parent', $input) && in_array($input['list_parent'], array('search', 'flagged', 'combined_inbox', 'unread', 'feeds'))) {
            if ($input['list_parent'] == 'combined_inbox') {
                $list_name = 'Everything';
            }
            else {
                $list_name = ucwords(str_replace('_', ' ', $input['list_parent']));
            }
            if ($input['list_parent'] == 'search') {
                $page = 'search';
            }
            else {
                $page = 'message_list';
            }
            $title = '<a href="?page='.$page.'&amp;list_path='.$this->html_safe($input['list_parent']).
                '">'.$this->html_safe($list_name).'</a>';
            if (array_key_exists('mailbox_list_title', $input) && count($input['mailbox_list_title'] > 1)) {
                $title .= '<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" />'.
                    '<a href="?page='.$page.'&amp;list_path='.$this->html_safe($input['list_path']).'">'.$this->html_safe($input['mailbox_list_title'][1]).'</a>';
            }
        }
        elseif (array_key_exists('mailbox_list_title', $input)) {
            $title = '<a href="?page=message_list&amp;list_path='.$this->html_safe($input['list_path']).'">'.
                implode('<img alt="" class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" />', $input['mailbox_list_title']).'</a>';
        }
        else {
            $title = '';
        }
        $res = '';
        if (array_key_exists('uid', $input)) {
            $res .= '<input type="hidden" class="msg_uid" value="'.$this->html_safe($input['uid']).'" />';
        }
        $res .= '<div class="content_title">'.$title.'</div>';
        $res .= '<div class="msg_text">';
        return $res;
    }
}

class Hm_Output_message_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</div>';
    }
}

class Hm_Output_notfound_content extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<div class="content_title">Page Not Found!</div>';
        $res .= '<div class="empty_list"><br />Nothingness</div>';
        return $res;
    }
}

class Hm_Output_dev_content extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="dev_content"><div class="content_title">Make it your own</div></div>';
    }
}

class Hm_Output_bug_report_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="bug_report"><div class="content_title">Is much broken</div></div>';
    }
}

class Hm_Output_help_content extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="help_content"><div class="content_title">Let me explain</div></div>';
    }
}

class Hm_Output_profile_content extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="profile_content"><div class="content_title">Profiles</div></div>';
    }
}

class Hm_Output_search_content extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<div class="search_content"><div class="content_title">Search'.
            search_form($input, $this).'</div>';
        $res .= '<table class="message_table">';
        if (!array_key_exists('no_message_list_headers', $input) || !$input['no_message_list_headers']) {
            $res .= '<colgroup><col class="chkbox_col"><col class="source_col">'.
            '<col class="from_col"><col class="subject_col"><col class="date_col">'.
            '<col class="icon_col"></colgroup><!--<thead><tr><th colspan="2" class="source">'.
            'Source</th><th class="from">From</th><th class="subject">Subject</th>'.
            '<th class="msg_date">Date</th><th></th></tr></thead>-->';
        }
        $res .= '<tbody></tbody></table>';
        return $res;
    }
}

class Hm_Output_message_list_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<table class="message_table">';
        if (!array_key_exists('no_message_list_headers', $input) || !$input['no_message_list_headers']) {
            $res .= '<colgroup><col class="chkbox_col"><col class="source_col">'.
            '<col class="from_col"><col class="subject_col"><col class="date_col">'.
            '<col class="icon_col"></colgroup><thead><tr><th></th><th class="source">'.
            'Source</th><th class="from">From</th><th class="subject">Subject</th>'.
            '<th class="msg_date">Date</th><th></th></tr></thead>';
        }
        $res .= '<tbody>';
        return $res;
    }
}

class Hm_Output_message_list_heading extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        $res .= '<div class="message_list"><div class="content_title">';
        $res .= message_controls().
            implode('<img alt="" class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" width="8" height="8" />', $input['mailbox_list_title']);
        $res .= '<div class="list_controls">';
        $res .= '<a onclick="return Hm_Message_List.load_sources()" href="#"><img alt="Refresh" class="refresh_list" src="'.Hm_Image_Sources::$refresh.'" width="20" height="20" /></a>';
        $res .= '</div>';
	    $res .= message_list_meta($input, $this);
        $res .= '</div>';
        return $res;
    }
}

class Hm_Output_message_list_end extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '</tbody></table><div class="page_links"></div></div>';
        return $res;
    }
}

?>
