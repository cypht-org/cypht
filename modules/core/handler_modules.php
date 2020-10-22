<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

/**
 * Check the folder list icon setting
 * @subpackage core/handler
 */
class Hm_Handler_check_folder_icon_setting extends Hm_Handler_Module {
    /***
     * set a flag to use folder list icons or not
     */
    public function process() {
        $this->out('hide_folder_icons', $this->user_config->get('no_folder_icons_setting', false));
    }
}

/**
 * Process a password update 
 * @subpackage core/handler
 */
class Hm_Handler_process_pw_update extends Hm_Handler_Module {
    /***
     * update a password in the session for a server
     */
    public function process() {
        list($success, $form ) = $this->process_form(array('server_pw_id', 'password'));
        $missing = $this->get('missing_pw_servers', array());
        if (!$success) {
            return;
        }
        if (!array_key_exists($form['server_pw_id'], $missing)) {
            return;
        }
        $server = $missing[$form['server_pw_id']];
        switch ($server['type']) {
            case 'POP3':
                $current = Hm_POP3_List::dump($server['id']);
                $current['pass'] = $form['password'];
                unset($current['nopass']);
                Hm_POP3_List::add($current, $server['id']);
                $pop3 = Hm_POP3_List::connect($server['id'], false);
                if ($pop3->state == 'authed') {
                    Hm_Msgs::add('Password Updated');
                    $this->out('connect_status', true);
                }
                else {
                    unset($current['pass']);
                    Hm_POP3_List::add($current, $server['id']);
                    Hm_Msgs::add('ERRUnable to authenticate to the POP3 server');
                    $this->out('connect_status', false);
                }
                break;
            case 'SMTP':
                $current = Hm_SMTP_List::dump($server['id']);
                $current['pass'] = $form['password'];
                unset($current['nopass']);
                Hm_SMTP_List::add($current, $server['id']);
                $smtp = Hm_SMTP_List::connect($server['id'], false);
                if ($smtp->state == 'authed') {
                    Hm_Msgs::add('Password Updated');
                    $this->out('connect_status', true);
                }
                else {
                    unset($current['pass']);
                    Hm_SMTP_List::add($current, $server['id']);
                    Hm_Msgs::add('ERRUnable to authenticate to the SMTP server');
                    $this->out('connect_status', false);
                }
                break;
            case 'IMAP':
                $current = Hm_IMAP_List::dump($server['id']);
                $current['pass'] = $form['password'];
                unset($current['nopass']);
                Hm_IMAP_List::add($current, $server['id']);
                $imap = Hm_IMAP_List::connect($server['id'], false);
                if ($imap->get_state() == 'authenticated') {
                    Hm_Msgs::add('Password Updated');
                    $this->out('connect_status', true);
                }
                else {
                    unset($current['pass']);
                    Hm_IMAP_List::add($current, $server['id']);
                    Hm_Msgs::add('ERRUnable to authenticate to the IMAP server');
                    $this->out('connect_status', false);
                }
                break;
        }
    }
}

/**
 * Check for missing passwords to populate a home page dialog 
 * @subpackage core/handler
 */
class Hm_Handler_check_missing_passwords extends Hm_Handler_Module {
    /***
     * pass a list of servers with missing passwords to the output modules
     */
    public function process() {
        if (!$this->user_config->get('no_password_save_setting')) {
            return;
        }
        $missing = array();
        if ($this->module_is_supported('imap')) {
            foreach (Hm_IMAP_List::dump() as $index => $vals) {
                if (array_key_exists('nopass', $vals)) {
                    $vals['id'] = $index;
                    $vals['type'] = 'IMAP';
                    $key = 'imap_'.$index;
                    $missing[$key] = $vals;
                }
            }
        }
        if ($this->module_is_supported('pop3')) {
            foreach (Hm_POP3_List::dump() as $index => $vals) {
                if (array_key_exists('nopass', $vals)) {
                    $vals['id'] = $index;
                    $vals['type'] = 'POP3';
                    $key = 'pop3_'.$index;
                    $missing[$key] = $vals;
                }
            }
        }
        if ($this->module_is_supported('smtp')) {
            foreach (Hm_SMTP_List::dump() as $index => $vals) {
                if (array_key_exists('nopass', $vals)) {
                    $vals['id'] = $index;
                    $vals['type'] = 'SMTP';
                    $key = 'smtp_'.$index;
                    $missing[$key] = $vals;
                }
            }
        }
        if (count($missing) > 0) {
            $this->out('missing_pw_servers', $missing);
        }
    }
}

/**
 * Close the session before it's automatically closed at the end of page processing
 * @subpackage core/handler
 */
class Hm_Handler_close_session_early extends Hm_Handler_Module {
    /***
     * Uses the close_early method of the session this->session object
     */
    public function process() {
        $this->session->close_early();
    }
}

/**
 * Build a list of HTTP headers to output to the browser
 * @subpackage core/handler
 */
class Hm_Handler_http_headers extends Hm_Handler_Module {
    /***
     * These are pretty restrictive, but the idea is to have a secure starting point
     */
    public function process() {
        $headers = array();
        if ($this->get('language')) {
            $headers['Content-Language'] = substr($this->get('language'), 0, 2);
        }
        if ($this->request->tls) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        $img_src = "'self'";
        if ($this->config->get('allow_external_image_sources', false)) {
            $img_src = '*';
        }
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', strtotime('-1 year'));
        $headers['Content-Security-Policy'] = "default-src 'none'; script-src 'self' 'unsafe-inline'; ".
            "connect-src 'self'; font-src 'self'; img-src ".$img_src." data:; style-src 'self' 'unsafe-inline';";
        if ($this->request->type == 'AJAX') {
            $headers['Content-Type'] = 'application/json';
        }
        $this->out('http_headers', $headers, false);
    }
}

/**
 * Process input from the the mailto handler setting in the general settings section.
 * @subpackage core/handler
 */
class Hm_Handler_process_mailto_handler_setting extends Hm_Handler_Module {
    /**
     * Can be one true or false
     */
    public function process() {
        function mailto_handler_callback($val) {
            return $val;
        }
        process_site_setting('mailto_handler', $this, 'mailto_handler_callback', false, true);
    }
}

/**
 * Process input from the the list style setting in the general settings section.
 * @subpackage core/handler
 */
class Hm_Handler_process_list_style_setting extends Hm_Handler_Module {
    /**
     * Can be one of two values, 'email_style' or 'list_style'. The default is 'email_style'.
     */
    public function process() {
        function list_style_callback($val) {
            if (in_array($val, array('email_style', 'news_style'))) {
                return $val;
            }
            return 'email_style';
        }
        process_site_setting('list_style', $this, 'list_style_callback');
    }
}

/**
 * Process input from the the start page setting in the general settings section.
 * @subpackage core/handler
 */
class Hm_Handler_process_start_page_setting extends Hm_Handler_Module {
    /**
     * Can be one of the values in start_page_opts()
     */
    public function process() {
        function start_page_callback($val) {
            if (in_array($val, start_page_opts(), true)) {
                return $val;
            }
            return false;
        }
        process_site_setting('start_page', $this, 'start_page_callback');
    }
}

/**
 * Check the default sort order
 * @subpackage core/handler
 */
class Hm_Handler_default_sort_order_setting extends Hm_Handler_Module {
    /***
     * retrieve default sort order of messages
     */
    public function process() {
        $this->out('default_sort_order', $this->user_config->get('default_sort_order_setting', 'arrival'));
    }
}

/**
 * Process input from the the default sort order setting in the general settings section.
 * @subpackage core/handler
 */
class Hm_Handler_process_default_sort_order_setting extends Hm_Handler_Module {
    /**
     * Can be one of the values in start_page_opts()
     */
    public function process() {
        function default_sort_order_callback($val) {
            if (in_array($val, array_keys(default_sort_order_opts()), true)) {
                return $val;
            }
            return false;
        }
        process_site_setting('default_sort_order', $this, 'default_sort_order_callback');
    }
}

/**
 * Process "hide folder list icons" setting 
 * @subpackage core/handler
 */
class Hm_Handler_process_hide_folder_icons extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function hide_folder_icons_callback($val) {
            return $val;
        }
        process_site_setting('no_folder_icons', $this, 'hide_folder_icons_callback', false, true);
    }
}

/**
 * Process "show icons in message lists" setting for the message list page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_show_list_icons extends Hm_Handler_Module {
    /**
     * valid values are true or false
     */
    public function process() {
        function show_list_icons_callback($val) {
            return $val;
        }
        process_site_setting('show_list_icons', $this, 'show_list_icons_callback', false, true);
    }
}

/**
 * Process input from the max per source setting for the Unread page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_unread_source_max_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('unread_per_source', $this, 'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * Process input from the max per source setting for the All E-mail page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_all_email_source_max_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('all_email_per_source', $this, 'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * Process input from the no pasword between logins setting
 * @subpackage core/handler
 */
class Hm_Handler_process_no_password_setting extends Hm_Handler_Module {
    /**
     * Allowed vals are bool true/false
     */
    public function process() {
        function no_password_callback($val) {
            return $val;
        }
        process_site_setting('no_password_save', $this, 'no_password_callback', false, true);
    }
}

/**
 * Process input from the disable delete prompts setting
 * @subpackage core/handler
 */
class Hm_Handler_process_delete_prompt_setting extends Hm_Handler_Module {
    /**
     * Allowed vals are bool true/false
     */
    public function process() {
        function delete_disabled_callback($val) {
            return $val;
        }
        process_site_setting('disable_delete_prompt', $this, 'delete_disabled_callback', false, true);
    }
}

/**
 * Process input from the max per source setting for the Everything page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_all_source_max_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('all_per_source', $this, 'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * Process input from the max per source setting for the Flagged page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_flagged_source_max_setting extends Hm_Handler_Module {
    /**
     * Allowed values are greater than zero and less than MAX_PER_SOURCE
     */
    public function process() {
        process_site_setting('flagged_per_source', $this,'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * Process "since" setting for the Flagged page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_flagged_since_setting extends Hm_Handler_Module {
    /**
     * valid values are defined in the process_since_argument function
     */
    public function process() {
        process_site_setting('flagged_since', $this, 'since_setting_callback');
    }
}

/**
 * Process "since" setting for the Everything page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_all_since_setting extends Hm_Handler_Module {
    /**
     * valid values are defined in the process_since_argument function
     */
    public function process() {
        process_site_setting('all_since', $this, 'since_setting_callback');
    }
}

/**
 * Process "since" setting for the All E-mail page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_all_email_since_setting extends Hm_Handler_Module {
    /**
     * valid values are defined in the process_since_argument function
     */
    public function process() {
        process_site_setting('all_email_since', $this, 'since_setting_callback');
    }
}

/**
 * Process "since" setting for the Unread page in the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_unread_since_setting extends Hm_Handler_Module {
    /**
     * valid values are defined in the process_since_argument function
     */
    public function process() {
        process_site_setting('unread_since', $this, 'since_setting_callback');
    }
}

/**
 * Process language setting from the general section of the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_language_setting extends Hm_Handler_Module {
    /**
     * compared against the list in modules/core/functions.php:interface_langs()
     */
    public function process() {
        function language_setting_callback($val) {
            if (array_key_exists($val, interface_langs())) {
                return $val;
            }
            return 'en';
        }
        process_site_setting('language', $this, 'language_setting_callback');
    }
}

/**
 * Process the timezone setting from the general section of the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_timezone_setting extends Hm_Handler_Module {
    public function process() {
        function timezone_setting_callback($val) {
            if (in_array($val, timezone_identifiers_list(), true)) {
                return $val;
            }
            return false;
        }
        process_site_setting('timezone', $this, 'timezone_setting_callback');
    }
}

/**
 * Save user settings permanently
 * @subpackage core/handler
 */
class Hm_Handler_process_save_form extends Hm_Handler_Module {
    /**
     * save any changes since login to permanent storage
     */
    public function process() {
        list($success, $form) = $this->process_form(array('password'));
        if (!$success) {
            return;
        }
        $save = false;
        $logout = false;
        if (array_key_exists('save_settings_permanently', $this->request->post)) {
            $save = true;
        }
        elseif (array_key_exists('save_settings_permanently_then_logout', $this->request->post)) {
            $save = true;
            $logout = true;
        }
        if ($save) {
            save_user_settings($this, $form, $logout);
        }
    }
}

/**
 * Save settings from the settings page to the session
 * @subpackage core/handler
 */
class Hm_Handler_save_user_settings extends Hm_Handler_Module {
    /**
     * save new site settings to the session
     */
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings'));
        if (!$success) {
            return;
        }
        if ($new_settings = $this->get('new_user_settings', array())) {
            foreach ($new_settings as $name => $value) {
                $this->user_config->set($name, $value);
            }
            Hm_Msgs::add('Settings updated');
            $this->session->record_unsaved('Site settings updated');
            $this->out('reload_folders', true, false);
        }
    }
}

/**
 * Setup a default title
 * @subpackage core/handler
 */
class Hm_Handler_title extends Hm_Handler_Module {
    /**
     * output a default title based on the page URL argument
     */
    public function process() {
        $this->out('title', ucfirst($this->page));
    }
}

/**
 * Setup the current language
 * @subpackage core/handler
 */
class Hm_Handler_language extends Hm_Handler_Module {
    /**
     * output the user configured language or English if not set
     */
    public function process() {
        $this->out('language', $this->user_config->get('language_setting', 'en'));
    }
}

/**
 * Setup the date
 * @subpackage core/handler
 */
class Hm_Handler_date extends Hm_Handler_Module {
    /**
     * output a simple date string
     */
    public function process() {
        $this->out('date', date('G:i:s'));
    }
}

/**
 * Check for the "stay logged in" option
 */
class Hm_Handler_stay_logged_in extends Hm_Handler_Module {
    /**
     * If "stay logged in" is checked, set the session lifetime
     */
    public function process() {
        if ($this->config->get('allow_long_session')) {
            $this->out('allow_long_session', true);
        }
        $lifetime = intval($this->config->get('long_session_lifetime', 30));
        list($success, $form) = $this->process_form(array('stay_logged_in'));
        if ($success && $form['stay_logged_in']) {
            $this->session->lifetime = time()+60*60*24*$lifetime;
        }
    }
}

/**
 * Process a potential login attempt
 * @subpackage core/handler
 */
class Hm_Handler_login extends Hm_Handler_Module {
    /**
     * Perform a new login if the form was submitted, otherwise check for and continue a session if it exists
     */
    public $validate_request = true;
    public function process() {
        $this->out('is_mobile', $this->request->mobile);
        if ($this->get('create_username', false)) {
            return;
        }
        list($success, $form) = $this->process_form(array('username', 'password'));
        if ($success) {
            $this->session->check($this->request, rtrim($form['username']), $form['password']);
            if ($this->session->auth_failed) {
                Hm_Msgs::add("ERRInvalid username or password");
            }
            $this->session->set('username', rtrim($form['username']));
            if ($this->config->get('redirect_after_login')) {
                $this->out('redirect_url', $this->config->get('redirect_after_login'));
            }
        }
        else {
            $this->session->check($this->request);
        }
        if ($this->session->is_active()) {
            $this->out('changed_settings', $this->session->get('changed_settings', array()), false);
            $this->out('username', $this->session->get('username'));
        }
        if ($this->validate_request) {
            Hm_Request_Key::load($this->session, $this->request, $this->session->loaded);
            $this->validate_method($this->session, $this->request);
            $this->process_key();
            if (!$this->config->get('disable_origin_check', false)) {
                $this->validate_origin($this->session, $this->request, $this->config);
            }
        }
    }
}

/**
 * Setup default page data
 * @subpackage core/handler
 */
class Hm_Handler_default_page_data extends Hm_Handler_Module {
    public function process() {
        $this->out('data_sources', array(), false);
        $this->out('encrypt_ajax_requests', $this->config->get('encrypt_ajax_requests', false));
        $this->out('encrypt_local_storage', $this->config->get('encrypt_local_storage', false));
        if (!crypt_state($this->config)) {
            $this->out('single_server_mode', true);
        }
    }
}

/**
 * Load user data
 * @subpackage core/handler
 */
class Hm_Handler_load_user_data extends Hm_Handler_Module {
    /**
     * Load data from persistant storage on login, or from the session if already logged in
     */
    public function process() {
        list($success, $form) = $this->process_form(array('username', 'password'));
        if ($this->session->is_active()) {
            if ($success) {
                $this->user_config->load(rtrim($form['username']), $form['password']);
            }
            else {
                $user_data = $this->session->get('user_data', array());
                if (!empty($user_data)) {
                    $this->user_config->reload($user_data, $this->session->get('username'));
                }
                $pages = $this->user_config->get('saved_pages', array());
                if (!empty($pages)) {
                    $this->session->set('saved_pages', $pages);
                }
            }
            $this->out('disable_delete_prompt', $this->user_config->get('disable_delete_prompt_setting'));
        }
        if ($this->session->loaded) {
            $start_page = $this->user_config->get('start_page_setting');
            if ($start_page && $start_page != 'none' && in_array($start_page, start_page_opts(), true)) {
                $this->out('redirect_url', '?'.$start_page);
            }
        }
        $this->out('mailto_handler', $this->user_config->get('mailto_handler_setting', false));
        $this->out('no_password_save', $this->user_config->get('no_password_save_setting', false));
        if (!strstr($this->request->server['REQUEST_URI'], 'page=') && $this->page == 'home') {
            $start_page = $this->user_config->get('start_page_setting', false);
            if ($start_page && $start_page != 'none' && in_array($start_page, start_page_opts(), true)) {
                Hm_Dispatch::page_redirect('?'.$start_page);
            }
        }
    }
}

/**
 * Save user data to the session
 * @subpackage core/handler
 */
class Hm_Handler_save_user_data extends Hm_Handler_Module {
    /**
     * @todo rename to make it obvious this is session only
     */
    public function process() {
        $user_data = $this->user_config->dump();
        if (!empty($user_data)) {
            $this->session->set('user_data', $user_data);
        }
    }
}

/**
 * Process a logout
 * @subpackage core/handler
 */
class Hm_Handler_logout extends Hm_Handler_Module {
    /**
     * Clean up everything on logout
     */
    public function process() {
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
    }
}

/**
 * Setup the message list type based on URL arguments
 * @subpackage core/handler
 */
class Hm_Handler_message_list_type extends Hm_Handler_Module {
    /**
     * @todo clean this up somehow
     */
    public function process() {
        $uid = '';
        $list_parent = '';
        $list_page = 1;
        $list_meta = true;
        $list_path = '';
        $mailbox_list_title = array();
        $message_list_since = DEFAULT_SINCE;
        $per_source_limit = DEFAULT_PER_SOURCE;
        $no_list_headers = false;

        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            list($list_path, $mailbox_list_title, $message_list_since, $per_source_limit) = get_message_list_settings($path, $this);
        }
        if (array_key_exists('list_parent', $this->request->get)) {
            $list_parent = $this->request->get['list_parent'];
        }
        if (array_key_exists('list_page', $this->request->get)) {
            $list_page = (int) $this->request->get['list_page'];
            if ($list_page < 1) {
                $list_page = 1;
            }
        }
        else {
            $list_page = 1;
        }
        if (array_key_exists('uid', $this->request->get) && preg_match("/\d+/", $this->request->get['uid'])) {
            $uid = $this->request->get['uid'];
        }
        $list_style = $this->user_config->get('list_style_setting', false);
        if ($this->get('is_mobile', false)) {
            $list_style = 'news_style';
        }
        if ($list_style == 'news_style') {
            $no_list_headers = true;
            $this->out('news_list_style', true);
        }
        $this->out('uid', $uid);
        $this->out('list_path', $list_path, false);
        $this->out('list_meta', $list_meta, false);
        $this->out('list_parent', $list_parent, false);
        $this->out('list_page', $list_page, false);
        $this->out('mailbox_list_title', $mailbox_list_title, false);
        $this->out('message_list_since', $message_list_since, false);
        $this->out('per_source_limit', $per_source_limit, false);
        $this->out('no_message_list_headers', $no_list_headers);
        $this->out('msg_list_icons', $this->user_config->get('show_list_icons_setting', false));
        $this->out('message_list_fields', array(
            array('chkbox_col', false, false),
            array('source_col', 'source', 'Source'),
            array('from_col', 'from', 'From'),
            array('subject_col', 'subject', 'Subject'),
            array('date_col', 'msg_date', 'Date'),
            array('icon_col', false, false)), false);
    }
}

/**
 * Set a cookie to instruct the JS to reload the folder list
 * @subpackage core/handler
 */
class Hm_Handler_reload_folder_cookie extends Hm_Handler_Module {
    /**
     * This cookie will be deleted by JS
     */
    public function process() {
        if ($this->get('reload_folders', false)) {
            $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
        }
    }
}

/**
 * @subpackage core/handler
 */
class Hm_Handler_reset_search extends Hm_Handler_Module {
    public function process() {
        $this->session->set('search_terms', '');
        $this->session->set('search_since', DEFAULT_SINCE);
        $this->session->set('search_fld', DEFAULT_SEARCH_FLD);
    }
}

/**
 * Process search terms from a URL
 * @subpackage core/handler
 */
class Hm_Handler_process_search_terms extends Hm_Handler_Module {
    /**
     * validate and set search tems in the session
     */
    public function process() {
        if (array_key_exists('search_terms', $this->request->get) && $this->request->get['search_terms']) {
            $this->out('run_search', 1, false);
            $this->session->set('search_terms', validate_search_terms($this->request->get['search_terms']));
        }
        if (array_key_exists('search_since', $this->request->get)) {
            $this->session->set('search_since', process_since_argument($this->request->get['search_since'], true));
        }
        if (array_key_exists('search_fld', $this->request->get)) {
            $this->session->set('search_fld', validate_search_fld($this->request->get['search_fld']));
        }
        $this->out('search_since', $this->session->get('search_since', DEFAULT_SINCE));
        $this->out('search_terms', $this->session->get('search_terms', ''));
        $this->out('search_fld', $this->session->get('search_fld', DEFAULT_SEARCH_FLD));
        if ($this->session->get('search_terms')) {
            $this->out('run_search', 1);
        }
    }
}

