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
class Hm_Handler_save_section_state extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('section_state', 'section_class'));
        if ($success && in_array($form['section_state'], array('block', 'none'))) {
            $state = $this->session->get('section_state', array());
            $state[$form['section_class']] = $form['section_state'] == 'block' ? 'none' : 'block';
            $this->session->set('section_state', $state);
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
        list($success, $form) = $this->process_form(array('save_settings'));
        if ($success && isset($data['new_user_settings'])) {
            foreach ($data['new_user_settings'] as $name => $value) {
                $this->user_config->set($name, $value);
            }
            $user = $this->session->get('username', false);
            $path = $this->config->get('user_settings_dir', false);
            if ($user && $path) {
                $this->user_config->save($user);
                Hm_Msgs::add('Settings saved');
            }
            Hm_Page_Cache::flush($this->session);
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
        $user_data = $this->session->get('user_data', array());
        if (!empty($user_data)) {
            $this->user_config->reload($user_data);
        }
        else {
            $user = $this->session->get('username', false);
            $this->user_config->load($user);
            $pages = $this->user_config->get('saved_pages', array());
            if (!empty($pages)) {
                $this->session->set('saved_pages', $pages);
            }
        }
        $data['section_state'] = $this->session->get('section_state', array());
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
            $user = $this->session->get('username', false);
            $path = $this->config->get('user_settings_dir', false);
            $pages = $this->session->get('saved_pages', array());
            if (!empty($pages)) {
                $this->user_config->set('saved_pages', $pages);
            }
            if ($user && $path) {
                $this->user_config->save($user);
                Hm_Msgs::add('Saved user data on logout');
            }
            $this->session->destroy();
            Hm_Msgs::add('Session destroyed on logout');
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
            }
            elseif ($path == 'flagged') {
                $data['list_path'] = 'flagged';
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
                $parts = explode('_', $path, 3);
                $details = Hm_POP3_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    if ($details['name'] == 'Default-Auth-Server') {
                        $details['name'] = 'Default';
                    }
                    $data['mailbox_list_title'] = array('POP3', $details['name'], 'INBOX');
                }
            }
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
        return $data;
    }
}


/* OUTPUT */

class Hm_Output_title extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<h1 class="title">HM3</h1>';
        }
    }
}

class Hm_Output_login extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
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
        }
        return '';
    }
}

class Hm_Output_date extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<div class="date">'.$this->html_safe($input['date']).'</div>';
        }
    }
}

class Hm_Output_logout extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' && $input['router_login_state']) {
            return '<form class="logout_form" method="POST"><input type="submit" class="logout" name="logout" value="Logout" /></form>';
        }
    }
}

class Hm_Output_msgs extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
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
        return '';
    }
}

class Hm_Output_header_start extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            $lang = '';
            if ($this->lang) {
                $lang = 'lang='.strtolower(str_replace('_', '-', $this->lang));
            }
            return '<!DOCTYPE html><html '.$lang.'><head>';
        }
        elseif ($format == 'CLI') {
            return sprintf("\nHM3 CLI Interface\n\n");
        }
    }
}

class Hm_Output_header_end extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '</head><body>';
        }
    }
}

class Hm_Output_content_start extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<body>';
        }
    }
}

class Hm_Output_header_content extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            $title = 'HM3';
            if (isset($input['mailbox_list_title'])) {
                $title .= ' '.implode('-', array_slice($input['mailbox_list_title'], 1));
            }
            elseif (isset($input['router_page_name'])) {
                if (isset($input['list_path']) && $input['router_page_name'] == 'message_list') {
                    $title .= ' '.ucfirst($input['list_path']);
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
}

class Hm_Output_header_css extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
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
}

class Hm_Output_page_js extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
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
}

class Hm_Output_content_end extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<div class="elapsed"></div></body></html>';
        }
    }
}

class Hm_Output_jquery extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<script type="text/javascript" src="modules/core/jquery-1.11.0.min.js"></script>';
        }
        return '';
    }
}

class Hm_Output_js_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<script type="text/javascript">'.
                'var hm_url_path = "'.$input['router_url_path'].'";'.
                'var hm_page_name = "'.$input['router_page_name'].'";'.
                'var hm_list_path = "'.(isset($input['list_path']) ? $input['list_path'] : '').'";'.
                '</script>';
        }
    }
}

class Hm_Output_loading_icon extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<div class="loading_icon"><img alt="Loading..." src="images/ajax-loader.gif" width="16" height="16" /></div>';
        }
    }
}

class Hm_Output_start_settings_form extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<div class="user_settings"><div class="content_title">Site Settings</div><br />'.
                '<form method="POST" action=""><table class="settings_table">';
        }
    }
}

class Hm_Output_language_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
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
}

class Hm_Output_timezone_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
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
}

class Hm_Output_end_settings_form extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<tr><td colspan="2" class="submit_cell">'.
                '<input class="save_settings" type="submit" name="save_settings" value="Save" />'.
                '</tr></table></form></div>';
        }
    }
}

class Hm_Output_toolbar_start extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '<div class="toolbar">';
        }
    }
}

class Hm_Output_toolbar_end extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5' ) {
            return '</div>';
        }
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
        $res .= '<div onclick="return toggle_section(\'.main\');" class="src_name">Main</div><div ';
        if (isset($input['section_state']['.main'])) {
            $res .= 'style="display: '.$this->html_safe($input['section_state']['.main']).'" ';
        }
        $res .= 'class="main"><ul class="folders">'.
            '<li class="menu_home"><a class="unread_link" href="?page=home">'.
            '<img class="account_icon" src="images/open_iconic/home-2x.png" alt="" /> '.$this->trans('Home').'</a></li>'.
            '<li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread">'.
            '<img class="account_icon" src="images/open_iconic/envelope-closed-2x.png" alt="" /> '.$this->trans('Unread').'</a></li>'.
            '<li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged">'.
            '<img class="account_icon" src="images/open_iconic/star-2x.png" alt="" /> '.$this->trans('Flagged').'</a></li>'.
            '<li class="menu_search"><a class="unread_link" href="?page=search">'.
            '<img class="account_icon" src="images/open_iconic/globe-2x.png" alt="" /> '.$this->trans('Search').'</a></li>'.
            '<li class="menu_compose"><a class="unread_link" href="?page=compose">'.
            '<img class="account_icon" src="images/open_iconic/document-2x.png" alt="" /> '.$this->trans('Compose').'</a></li>'.
            '</ul></div>';
        if (isset($input['folder_sources'])) {
            foreach ($input['folder_sources'] as $src) {
                $name = strtoupper(explode('_', $src)[0]);
                $res .= '<div onclick="return toggle_section(\'.'.$this->html_safe($src).'\');" class="src_name">'.$this->html_safe($name).'</div>';
                $res .= '<div ';
                if (isset($input['section_state']['.'.$src])) {
                    $res .= 'style="display: '.$this->html_safe($input['section_state']['.'.$src]).'" ';
                }
                $res .= 'class="'.$this->html_safe($src).'">';
                $cache = Hm_Page_Cache::get($src);
                if ($cache) {
                    $res .= $cache;
                }
                $res .= '</div>';
            }
        }
        $res .= '<div onclick="return toggle_section(\'.settings\');" class="src_name">Settings</div><ul ';

        if (isset($input['section_state']['.settings'])) {
            $res .= 'style="display: '.$this->html_safe($input['section_state']['.settings']).'" ';
        }
        $res .= 'class="settings folders">'.
            '<li class="menu_servers"><a class="unread_link" href="?page=servers">'.
            '<img class="account_icon" src="images/open_iconic/monitor-2x.png" alt="" /> '.$this->trans('Servers').'</a></li>'.
            '<li class="menu_settings"><a class="unread_link" href="?page=settings">'.
            '<img class="account_icon" src="images/open_iconic/cog-2x.png" alt="" /> '.$this->trans('Site').'</a></li>'.
            '<li class="menu_profiles"><a class="unread_link" href="?page=profiles">'.
            '<img class="account_icon" src="images/open_iconic/people-2x.png" alt="" /> '.$this->trans('Profiles').'</a></li>'.
            '</ul></div>';

        return $res;
    }
}

class Hm_Output_folder_list_end extends Hm_Output_Module {
    protected function output($input, $format) {
        return '</div>';
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

class Hm_Output_message_start extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['uid'])) {
            $res .= '<input type="hidden" class="msg_uid" value="'.$this->html_safe($input['uid']).'" />';
        }
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

?>
