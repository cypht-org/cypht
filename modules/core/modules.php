<?php

if (!defined('DEBUG_MODE')) { die(); }

/* INPUT */

class Hm_Handler_http_headers extends Hm_Handler_Module {
    public function process($data) {
        if (isset($data['language'])) {
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
            if (isset($data['new_user_settings'])) {
                foreach ($data['new_user_settings'] as $name => $value) {
                    $this->user_config->set($name, $value);
                }
                $user = $this->session->get('username', false);
                $path = $this->config->get('user_settings_dir', false);
                if ($this->session->auth($user, $form['password'])) {
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
        elseif (isset($this->request->post['save_settings'])) {
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
        $data['date'] = date('Y-m-d h:i:s');
        return $data;
    }
}

class Hm_Handler_login extends Hm_Handler_Module {
    public function process($data) {
        if (!isset($this->request->post['create_hm_user'])) {
            list($success, $form) = $this->process_form(array('username', 'password'));
            if ($success) {
                $this->session->check($this->request, $form['username'], $form['password']);
                $this->session->set('username', $form['username']);
            }
            else {
                $this->session->check($this->request);
            }
            $data['session_type'] = get_class($this->session);
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
            $this->session->create($this->request, $form['username'], $form['password']);
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
        if (isset($this->request->post['logout']) && !$this->session->loaded) {
            $this->session->destroy($this->request);
            Hm_Msgs::add('Session destroyed on logout');
        }
        elseif (isset($this->request->post['save_and_logout'])) {
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
        if (isset($this->request->get['list_path'])) {
            $path = $this->request->get['list_path'];
            if ($path == 'unread') {
                $data['list_path'] = 'unread';
                $data['mailbox_list_title'] = array('Unread');
            }
            elseif ($path == 'feeds') {
                $data['list_path'] = 'feeds';
                $data['mailbox_list_title'] = array('Feeds');
            }
            elseif ($path == 'flagged') {
                $data['list_path'] = 'flagged';
                $data['mailbox_list_title'] = array('Flagged');
            }
            elseif ($path == 'combined_inbox') {
                $data['list_path'] = 'combined_inbox';
                $data['mailbox_list_title'] = array('Everything');
            }
            elseif (preg_match("/^imap_\d+_[^\s]+/", $path)) {
                $data['list_path'] = $path;
                $parts = explode('_', $path, 3);
                $details = Hm_IMAP_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    $data['mailbox_list_title'] = array('IMAP', $details['name'], $parts[2]);
                }
            }
            elseif (preg_match("/^pop3_\d+/", $path)) {
                $data['list_path'] = $path;
                $parts = explode('_', $path, 2);
                $details = Hm_POP3_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    if ($details['name'] == 'Default-Auth-Server') {
                        $details['name'] = 'Default';
                    }
                    $data['mailbox_list_title'] = array('POP3', $details['name'], 'INBOX');
                }
            }
            elseif (preg_match("/^feeds_\d+/", $path)) {
                $data['list_path'] = $path;
                $parts = explode('_', $path, 2);
                $details = Hm_Feed_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    $data['mailbox_list_title'] = array('Feeds', $details['name']);
                }
            }
        }
        if (isset($this->request->get['list_parent'])) { //&& in_array($this->request->get['list_parent'], array('unread', 'flagged', 'combined_inbox'))) {
            $data['list_parent'] = $this->request->get['list_parent'];
        }
        else {
            $data['list_parent'] = false;
        }
        if (isset($this->request->get['list_page'])) {
            $data['list_page'] = (int) $this->request->get['list_page'];
            if ($data['list_page'] < 1) {
                $data['list_page'] = 1;
            }
        }
        else {
            $data['list_page'] = 1;
        }
        if (isset($this->request->get['uid']) && preg_match("/\d+/", $this->request->get['uid'])) {
            $data['uid'] = $this->request->get['uid'];
        }
        $list_style = $this->user_config->get('list_style', false);
        if ($list_style == 'news_style') {
            $data['no_message_list_headers'] = true;
            $data['news_list_style'] = true;
        }
        return $data;
    }
}

class Hm_Handler_reload_folder_cookie extends Hm_Handler_Module {
    public function process($data) {
        if (isset($data['reload_folders'])) {
            secure_cookie($this->request, 'hm_reload_folders', '1');
        }
    }
}


/* OUTPUT */

class Hm_Output_title extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<h1 class="title">HM3</h1>';
    }
}

class Hm_Output_login extends Hm_Output_Module {
    protected function output($input, $format) {
        if (!$input['router_login_state']) {
            $res = '<form class="login_form" method="POST">'.
                '<h1 class="title">HM3</h1>'.
                ' <input type="text" placeholder="'.$this->trans('Username').'" name="username" value="">'.
                ' <input type="password" placeholder="'.$this->trans('Password').'" name="password">'.
                ' <input type="submit" value="Login" />';
            if (($input['session_type'] == 'Hm_DB_Session_DB_Auth' || $input['session_type'] == 'Hm_PHP_Session_DB_Auth') &&
                $input['router_page_name'] == 'home') {
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

class Hm_Output_logout extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<form class="logout_form" method="POST">'.
            '<input type="button" onclick="return confirm_logout()" class="logout" value="Logout" />'.
            '<div class="confirm_logout"><div class="confirm_text">You must enter your password to save your settings on logout</div>'.
            '<input name="password" class="save_settings_password" type="password" placeholder="Password" />'.
            '<input class="save_settings" type="submit" name="save_and_logout" value="Save and Logout" />'.
            '<input class="save_settings" type="submit" name="logout" value="Just Logout" />'.
            '<input class="save_settings" onclick="$(\'.confirm_logout\').fadeOut(200); return false;" type="button" value="Cancel" />'.
            '</div>'.
            '</form>';
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
            $res .= ' style="display: none;">';
        }
        return $res;
    }
}

class Hm_Output_header_content extends Hm_Output_Module {
    protected function output($input, $format) {
        $title = 'HM3';
        if (isset($input['mailbox_list_title'])) {
            $title .= ' '.implode('-', array_slice($input['mailbox_list_title'], 1));
        }
        elseif (isset($input['router_page_name'])) {
            if (isset($input['list_path']) && $input['router_page_name'] == 'message_list') {
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
            '<link rel="icon" type="image/png" href="images/open_iconic/envelope-closed-2x.png">'.
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
        return '<div class="elapsed"></div></body></html>';
    }
}

class Hm_Output_jquery extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<script type="text/javascript" src="modules/core/jquery-1.11.0.min.js"></script>';
    }
}

class Hm_Output_js_data extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<script type="text/javascript">'.
            'var hm_url_path = "'.$this->html_safe($input['router_url_path']).'";'.
            'var hm_page_name = "'.$this->html_safe($input['router_page_name']).'";'.
            'var hm_list_path = "'.(isset($input['list_path']) ? $this->html_safe($input['list_path']) : '').'";'.
            'var hm_list_parent = "'.(isset($input['list_parent']) ? $this->html_safe($input['list_parent']) : '').'";'.
            'var hm_msg_uid = "'.(isset($input['uid']) ? $this->html_safe($input['uid']) : 0).'";'.
            'var hm_module_list = "'.$this->html_safe($input['router_module_list']).'";'.
            '</script>';
    }
}

class Hm_Output_loading_icon extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="loading_icon"><img alt="Loading..." src="'.Hm_Image_Sources::$spinner.'" width="16" height="16" /></div>';
    }
}

class Hm_Output_start_settings_form extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="user_settings"><div class="content_title">Site Settings</div><br />'.
            '<form method="POST" action=""><table class="settings_table"><colgroup>'.
            '<col class="label_col"><col class="setting_col"></colgroup>';
    }
}

class Hm_Output_list_style_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $options = array('email_style' => 'Email', 'news_style' => 'News');

        if (isset($input['user_settings']['list_style'])) {
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

class Hm_Output_language_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $langs = array(
            'en_US' => 'English',
            'es_ES' => 'Spanish'
        );
        if (isset($input['user_settings']['language'])) {
            $mylang = $input['user_settings']['language'];
        }
        else {
            $mylang = false;
        }
        $res = '<tr><td>Interface Language</td><td><select name="language_setting">';
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
        if (isset($input['user_settings']['timezone'])) {
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

class Hm_Output_toolbar_start extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="toolbar">';
    }
}

class Hm_Output_toolbar_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</div>';
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
        $res = '<div class="folder_cell"><div class="folder_list">';
        return $res;
    }
}

class Hm_Output_folder_list_content extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = main_menu($input, $this);
        $res .= folder_source_menu($input, $this);
        $res .= settings_menu($input, $this);
        $res .= '<a href="#" onclick="return update_folder_list();" class="update_message_list">[reload]</a>';
        if ($format == 'HTML5') {
            return $res;
        }
        $input['formatted_folder_list'] = $res;
        return $input;
    }
}

function main_menu ($input, $output_mod) {
    $email = false;
    $feeds = false;
    if (isset($input['folder_sources']) && is_array($input['folder_sources'])) {
        if (in_array('pop3_folders', $input['folder_sources']) || in_array('imap_folders', $input['folder_sources'])) {
            $email = true;
        }
        if (in_array('feeds_folders', $input['folder_sources'])) {
            $feeds = true;
        }
    }
    $res = '<div class="src_name">Main'.
        '<img class="menu_caret" onclick="return toggle_section(\'.main\');" src="'.Hm_Image_Sources::$chevron.'" />'.
        '</div><div ';
    $res .= 'class="main"><ul class="folders">'.
        '<li class="menu_home"><a class="unread_link" href="?page=home">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$home).'" alt="" /> '.$output_mod->trans('Home').'</a></li>'.
        '<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$box).'" alt="" /> '.$output_mod->trans('Everything').'</a></li>';
    if ($email) {
        $res .= '<li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread">'.
            '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$env_closed).'" alt="" /> '.$output_mod->trans('Unread').
            ' <span class="unread_count"></span></a></li>';
    }
    if ($feeds) {
        $res .= '<li class="menu_feeds"><a class="unread_link" href="?page=message_list&amp;list_path=feeds">'.
            '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$env_closed).'" alt="" /> '.$output_mod->trans('Feeds').'</a></li>';
    }
    $res .= '<li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$star).'" alt="" /> '.$output_mod->trans('Flagged').'</a></li>'.
        '<!--<li class="menu_search"><a class="unread_link" href="?page=search">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$globe).'" alt="" /> '.$output_mod->trans('Search').'</a></li>-->'.
        '<li class="menu_compose"><a class="unread_link" href="?page=compose">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$doc).'" alt="" /> '.$output_mod->trans('Compose').'</a></li>'.
        '</ul></div>';
    return $res;
}
function folder_source_menu( $input, $output_mod) {
    $res = '';
    if (isset($input['folder_sources'])) {
        foreach ($input['folder_sources'] as $src) {
            $name = ucfirst(strtolower(explode('_', $src)[0]));
            $res .= '<div class="src_name">'.$output_mod->html_safe($name).
                '<img class="menu_caret" onclick="return toggle_section(\'.'.$output_mod->html_safe($src).
                '\');" src="'.Hm_Image_Sources::$chevron.'" /></div>';
            $res .= '<div style="display: none;" ';
            $res .= 'class="'.$output_mod->html_safe($src).'">';
            $cache = Hm_Page_Cache::get($src);
            if ($cache) {
                $res .= $cache;
            }
            $res .= '</div>';
        }
    }
    return $res;
}
function settings_menu( $input, $output_mod) {
    return '<div class="src_name">Settings'.
        '<img class="menu_caret" onclick="return toggle_section(\'.settings\');" src="'.Hm_Image_Sources::$chevron.'" />'.
        '</div><ul style="display: none;" class="settings folders">'.
        '<li class="menu_servers"><a class="unread_link" href="?page=servers">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$monitor).'" alt="" /> '.$output_mod->trans('Servers').'</a></li>'.
        '<li class="menu_settings"><a class="unread_link" href="?page=settings">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$cog).'" alt="" /> '.$output_mod->trans('Site').'</a></li>'.
        '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">'.
        '<img class="account_icon" src="'.$output_mod->html_safe(Hm_Image_Sources::$people).'" alt="" /> '.$output_mod->trans('Profiles').'</a></li>'.
        '</ul></div>';
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

class Hm_Output_server_summary_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<div class="server_summary"><div class="content_title">Summary</div>';
        $res .= '<table><thead><tr><th>Type</th><th>Name</th><th>Address</th><th>Port</th>'.
                '<th>TLS</th></tr></thead><tbody>';
        return $res;
    }
}

class Hm_Output_server_status_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<div class="server_status"><div class="content_title">Status</div>';
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
        if (isset($input['list_parent']) && in_array($input['list_parent'], array('flagged', 'combined_inbox', 'unread', 'feeds'))) {
            if ($input['list_parent'] == 'combined_inbox') {
                $list_name = 'Everything';
            }
            else {
                $list_name = ucwords(str_replace('_', ' ', $input['list_parent']));
            }
            $title = '<a href="?page=message_list&amp;list_path='.$this->html_safe($input['list_parent']).
                '">'.$this->html_safe($list_name).'</a>';
            if (isset($input['mailbox_list_title']) && count($input['mailbox_list_title'] > 1)) {
                $title .= ' - '.$this->html_safe($input['mailbox_list_title'][1]);
            }
        }
        elseif (isset($input['mailbox_list_title'])) {
            $title = '<a href="?page=message_list&amp;list_path='.$this->html_safe($input['list_path']).'">'.
                implode('<img class="path_delim" src="images/open_iconic/caret-right.png" alt="&gt;" />', $input['mailbox_list_title']).'</a>';
        }
        else {
            $title = '';
        }
        $res = '';
        if (isset($input['uid'])) {
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
        $res = '<div class="not_found">Page Not Found!</div>';
        return $res;
    }
}

class Hm_Output_profile_content extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="profile_content"><div class="content_title">Profiles</div></div>';
    }
}

class Hm_Output_search_content extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<div class="search_content"><div class="content_title">Search</div></div>';
    }
}

class Hm_Output_server_summary_end extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ((!isset($input['imap_servers']) || empty($input['imap_servers'])) &&
            (!isset($input['pop3_servers']) || empty($input['pop3_servers']))) {
            $res .= '<tr><td colspan="5"><div class="no_servers">No IMAP or POP3 Servers configured! You should <a href="?page=servers">add some</a>.</div></td></tr>';
        }
        $res .= '</tbody></table></div>';
        return $res;
    }
}

class Hm_Output_message_list_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<table class="message_table" cellpadding="0" cellspacing="0">';
        if (!isset($input['no_message_list_headers']) || !$input['no_message_list_headers']) {
            $res .= '<colgroup><col class="chkbox_col"><col class="source_col">'.
            '<col class="from_col"><col class="subject_col"><col class="date_col">'.
            '<col class="icon_col"></colgroup><thead><tr><th colspan="2" class="source">'.
            'Source</th><th class="from">From</th><th class="subject">Subject</th>'.
            '<th class="msg_date">Date</th><th></th></tr></thead>';
        }
        $res .= '<tbody>';
        return $res;
    }
}

class Hm_Output_message_list_heading extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '<div class="message_list"><div class="content_title">'.
            implode('<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" />', $input['mailbox_list_title']);

        if ($input['list_path'] == 'unread' || $input['list_path'] == 'feeds') {
            if (isset($input['message_list_since'])) {
                $since = $input['message_list_since'];
            }
            else {
                $since = 'today';
            }
            $res .= message_since_dropdown($since);
        }

        $res .= ' <a class="update_message_list" onclick="return Hm_Message_List.load_sources()"'.
            ' href="#">[update]</a></div>'.message_controls();

        return $res;
    }
}

class Hm_Output_message_list_end extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '</tbody></table><div class="page_links"></div></div>';
        return $res;
    }
}

function human_readable_interval($date_str) {
    $precision     = 2;
    $interval_time = array();
    $now           = time();
    $date          = strtotime($date_str);
    $interval      = $now - $date;
    $res           = array();

    $t['second'] = 1;
    $t['minute'] = $t['second']*60;
    $t['hour']   = $t['minute']*60;
    $t['day']    = $t['hour']*24;
    $t['week']   = $t['day']*7;
    $t['month']  = $t['day']*30;
    $t['year']   = $t['week']*52;

    if ($interval < 0) {
        return 'From the future!';
    }

    foreach (array_reverse($t) as $name => $val) {
        if ($interval_time[$name] = ($interval/$val > 0) ? floor($interval/$val) : false) {
            $interval -= $val*$interval_time[$name];
        }
    }

    $interval_time = array_slice(array_filter($interval_time, function($v) { return $v > 0; }), 0, $precision);

    foreach($interval_time as $name => $val) {
        if ($val > 1) {
            $res[] = sprintf('%d %ss', $val, $name);
        }
        else {
            $res[] = sprintf('%d %s', $val, $name);
        }
    }
    return implode(', ', $res);
}

function message_list_row($subject, $date, $timestamp, $from, $source, $id, $flags, $style, $url, $output_mod) {
        if ($style == 'email') {
            return array(
                '<tr style="display: none;" class="'.$output_mod->html_safe($id).'">'.
                    '<td class="checkbox_cell"><input type="checkbox" value="'.$output_mod->html_safe($id).'" /></td>'.
                    '<td class="source">'.$output_mod->html_safe($source).'</td>'.
                    '<td class="from">'.$output_mod->html_safe($from).'</td>'.
                    '<td class="subject"><div class="'.$output_mod->html_safe(implode(' ', $flags)).'">'.
                        '<a href="'.$output_mod->html_safe($url).'">'.$output_mod->html_safe($subject).'</a>'.
                    '</div></td>'.
                    '<td class="msg_date">'.$date.'<input type="hidden" class="msg_timestamp" value="'.$output_mod->html_safe($timestamp).'" /></td>'.
                    '<td class="icon">'.(in_array('flagged', $flags) ? '<img src="'.Hm_Image_Sources::$star.'" />' : '').'</td>'.
                '</tr>', $id);
        }
        else {
            return array(
                '<tr style="display: none;" class="'.$output_mod->html_safe($id).'">'.
                    '<td class="news_cell checkbox_cell"><input type="checkbox" value="'.$output_mod->html_safe($id).'" /></td>'.
                    '<td class="news_cell"><div class="icon">'.(in_array('flagged', $flags) ? '<img src="'.Hm_Image_Sources::$star.'" />' : '').'</div>'.
                    '<div class="subject"><div class="'.$output_mod->html_safe(implode(' ', $flags)).'">'.
                        '<a href="'.$output_mod->html_safe($url).'">'.$output_mod->html_safe($subject).'</a>'.
                    '</div></div>'.
                    '<div class="from">'.$output_mod->html_safe($from).' - '.$output_mod->html_safe($source).'</div>'.
                    '<div class="msg_date">'.$date.'<input type="hidden" class="msg_timestamp" value="'.$output_mod->html_safe($timestamp).'" /></div>'.
                '</td></tr>', $id);
        }
}

function message_controls() {
    return '<div class="msg_controls">'.
        '<a class="toggle_link" href="#" onclick="return toggle_rows();"><img src="'.Hm_Image_Sources::$check.'" /></a>'.
        '<a href="#" onclick="return message_action(\'read\');" class="disabled_link">Read</a>'.
        '<!--<a href="#" onclick="return message_action(\'unread\');" class="disabled_link">Unread</a>-->'.
        '<a href="#" onclick="return message_action(\'flag\');" class="disabled_link">Flag</a>'.
        '<a href="#" onclick="return message_action(\'unflag\');" class="disabled_link">Unflag</a>'.
        '<a href="#" onclick="return message_action(\'delete\');" class="disabled_link">Delete</a></div>';
}

function message_since_dropdown($since) {
    $times = array(
        'today' => 'Today',
        '-1 week' => 'Last 7 days',
        '-2 weeks' => 'Last 2 weeks',
        '-4 weeks' => 'Last 4 weeks',
        '-6 weeks' => 'Last 6 weeks',
        '-6 months' => 'Last 6 months',
        '-1 year' => 'Last year'
    );
    $res = '<select class="message_list_since">';
    foreach ($times as $val => $label) {
        $res .= '<option';
        if ($val == $since) {
            $res .= ' selected="selected"';
        }
        $res .= ' value="'.$val.'">'.$label.'</option>';
    }
    $res .= '</select>';
    return $res;
}

function process_since_argument($val, $config) {
    $date = false;
    if (in_array($val, array('-1 week', '-2 weeks', '-4 weeks', '-6 weeks', '-6 months', '-1 year'))) {
        $date = date('j-M-Y', strtotime($val));
        $config->set('message_list_since', $val);
    }
    elseif ($val == 'today') {
        $date = date('j-M-Y');
        $config->set('message_list_since', $val);
    }
    return $date;
}

function format_msg_html($str) {
    require 'lib/HTMLPurifier.standalone.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    $config->set('URI.DisableResources', true);
    $config->set('URI.DisableExternalResources', true);
    $config->set('URI.DisableExternal', true);
    $config->set('HTML.TargetBlank', true);
    $config->set('Filter.ExtractStyleBlocks.TidyImpl', true);
    $purifier = new HTMLPurifier($config);
    $res = $purifier->purify($str);
    return $res;
}

function format_msg_image($str, $mime_type) {
    return '<img src="data:image/'.$mime_type.';base64,'.chunk_split(base64_encode($str)).'" />';
}

function format_msg_text($str, $output_mod) {
    $link_regex = "/((http|ftp|rtsp)s?:\/\/(%[[:digit:]A-Fa-f][[:digit:]A-Fa-f]|[-_\.!~\*';\/\?#:@&=\+$,\[\]%[:alnum:]])+)/m";
    $str = nl2br(str_replace(' ', '&#160;&#8203;', ($output_mod->html_safe($str))));
    $str = preg_replace($link_regex, "<a target=\"_blank\" href=\"$1\">$1</a>", $str);

    return $str;
}

function build_msg_gravatar($from) {
    if (preg_match("/[\S]+\@[\S]+/", $from, $matches)) {
        $hash = md5(strtolower(trim($matches[0], " \"><'\t\n\r\0\x0B")));
        return '<img class="gravatar" src="http://www.gravatar.com/avatar/'.$hash.'?d=mm" />';
    }
}

function display_value($name, $haystack, $type=false, $default='') {
    if (!isset($haystack[$name])) {
        return $default;
    }
    $value = $haystack[$name];
    $res = false;
    if ($type) {
        $name = $type;
    }
    switch($name) {
        case 'from':
            $value = preg_replace("/(\<.+\>)/U", '', $value);
            $res = str_replace('"', '', $value);
            break;
        case 'date':
            $res = human_readable_interval($value);
            break;
        case 'time':
            $res = strtotime($value);
            break;
        default:
            $res = $value;
            break;
    }
    return $res;
}

?>
