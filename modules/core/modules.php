<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

if (!defined('DEBUG_MODE')) { die(); }

define('MAX_PER_SOURCE', 100);
define('DEFAULT_PER_SOURCE', 20);
define('DEFAULT_SINCE', 'today');

require APP_PATH.'modules/core/functions.php';

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
            $headers[] = 'Content-Language: '.substr($this->get('language'), 0, 2);
        }
        if ($this->request->tls) {
            $headers[] = 'Strict-Transport-Security: max-age=31536000';
        }
        $headers[] = 'X-XSS-Protection: 1; mode=block';
        $headers[] = 'X-Content-Type-Options: nosniff';
        $headers[] = 'Expires: '.gmdate('D, d M Y H:i:s \G\M\T', strtotime('-1 year'));
        $headers[] = "Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline';";
        if ($this->request->type == 'AJAX') {
            $headers[] = 'Content-Type: application/json';
        }
        $this->out('http_headers', $headers);
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
        list($success, $form) = $this->process_form(array('save_settings', 'list_style'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if (in_array($form['list_style'], array('email_style', 'news_style'))) {
                $new_settings['list_style'] = $form['list_style'];
            }
            else {
                $settings['list_style'] = $this->user_config->get('list_style', false);
            }
        }
        else {
            $settings['list_style'] = $this->user_config->get('list_style', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        list($success, $form) = $this->process_form(array('save_settings', 'unread_per_source'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if ($form['unread_per_source'] > MAX_PER_SOURCE || $form['unread_per_source'] < 0) {
                $sources = DEFAULT_PER_SOURCE;
            }
            else {
                $sources = $form['unread_per_source'];
            }
            $new_settings['unread_per_source_setting'] = $sources;
        }
        else {
            $settings['unread_per_source'] = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        list($success, $form) = $this->process_form(array('save_settings', 'all_email_per_source'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if ($form['all_email_per_source'] > MAX_PER_SOURCE || $form['all_email_per_source'] < 0) {
                $sources = DEFAULT_PER_SOURCE;
            }
            else {
                $sources = $form['all_email_per_source'];
            }
            $new_settings['all_email_per_source_setting'] = $sources;
        }
        else {
            $settings['all_email_per_source'] = $this->user_config->get('all_email_per_source_setting', DEFAULT_PER_SOURCE);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        list($success, $form) = $this->process_form(array('save_settings', 'all_per_source'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if ($form['all_per_source'] > MAX_PER_SOURCE || $form['all_per_source'] < 0) {
                $sources = DEFAULT_PER_SOURCE;
            }
            else {
                $sources = $form['all_per_source'];
            }
            $new_settings['all_per_source_setting'] = $sources;
        }
        else {
            $settings['all_per_source'] = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        list($success, $form) = $this->process_form(array('save_settings', 'flagged_per_source'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if ($form['flagged_per_source'] > MAX_PER_SOURCE || $form['flagged_per_source'] < 0) {
                $sources = DEFAULT_PER_SOURCE;
            }
            else {
                $sources = $form['flagged_per_source'];
            }
            $new_settings['flagged_per_source_setting'] = $sources;
        }
        else {
            $settings['flagged_per_source'] = $this->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
        }
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());
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
        list($success, $form) = $this->process_form(array('save_settings', 'flagged_since'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['flagged_since_setting'] = process_since_argument($form['flagged_since'], true);
        }
        else {
            $settings['flagged_since'] = $this->user_config->get('flagged_since_setting', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        list($success, $form) = $this->process_form(array('save_settings', 'all_since'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['all_since_setting'] = process_since_argument($form['all_since'], true);
        }
        else {
            $settings['all_since'] = $this->user_config->get('all_since_setting', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        list($success, $form) = $this->process_form(array('save_settings', 'all_email_since'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['all_email_since_setting'] = process_since_argument($form['all_email_since'], true);
        }
        else {
            $settings['all_email_since'] = $this->user_config->get('all_email_since_setting', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        list($success, $form) = $this->process_form(array('save_settings', 'unread_since'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['unread_since_setting'] = process_since_argument($form['unread_since'], true);
        }
        else {
            $settings['unread_since'] = $this->user_config->get('unread_since_setting', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Process language setting from the general section of the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_language_setting extends Hm_Handler_Module {
    /**
     * @todo add validation
     */
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'language_setting'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['language_setting'] = $form['language_setting'];
        }
        else {
            $settings['language'] = $this->user_config->get('language_setting', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Process the timezone setting from the general section of the settings page
 * @subpackage core/handler
 */
class Hm_Handler_process_timezone_setting extends Hm_Handler_Module {
    /**
     * @todo: add validation
     */
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'timezone_setting'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['timezone_setting'] = $form['timezone_setting'];
        }
        else {
            $settings['timezone'] = $this->user_config->get('timezone_setting', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
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
        $save = false;
        $logout = false;
        if ($success) {
            if (array_key_exists('save_settings_permanently', $this->request->post)) {
                $save = true;
            }
            elseif (array_key_exists('save_settings_permanently_then_logout', $this->request->post)) {
                $save = true;
                $logout = true;
            }
            if ($save) {
                $user = $this->session->get('username', false);
                $path = $this->config->get('user_settings_dir', false);

                if ($this->session->auth($user, $form['password'])) {
                    $pass = $form['password'];
                }
                else {
                    Hm_Msgs::add('ERRIncorrect password, could not save settings to the server');
                    $pass = false;
                }
                if ($user && $path && $pass) {
                    $this->user_config->save($user, $pass);
                    $this->session->set('changed_settings', array());
                    if ($logout) {
                        $this->session->destroy($this->request);
                        Hm_Msgs::add('Saved user data on logout');
                        Hm_Msgs::add('Session destroyed on logout');
                    }
                    else {
                        Hm_Msgs::add('Settings saved');
                    }
                }
            }
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
        if ($success) {
            if ($new_settings = $this->get('new_user_settings', array())) {
                foreach ($new_settings as $name => $value) {
                    $this->user_config->set($name, $value);
                }
                Hm_Page_Cache::flush($this->session);
                Hm_Msgs::add('Settings saved');
                $this->session->record_unsaved('Site settings updated');
                $this->out('reload_folders', true, false);
            }
        }
        /*elseif (array_key_exists('save_settings', $this->request->post)) {
            Hm_Msgs::add('ERRYour password is required to save your settings to the server');
        }*/
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
 * Process a potential login attempt
 * @subpackage core/handler
 */
class Hm_Handler_login extends Hm_Handler_Module {
    /**
     * Perform a new login if the form was submitted, otherwise check for and continue a session if it exists
     */
    public function process() {
        if (!$this->get('create_username', false)) {
            list($success, $form) = $this->process_form(array('username', 'password'));
            if ($success) {
                $this->session->check($this->request, $form['username'], $form['password']);
                $this->session->set('username', $form['username']);
            }
            else {
                $this->session->check($this->request);
            }
            if ($this->session->is_active()) {
                Hm_Page_Cache::load($this->session);
                $this->out('changed_settings', $this->session->get('changed_settings', array()), false);
            }
        }
        $this->process_nonce();
    }
}

/**
 * Setup default page data
 * @subpackage core/handler
 */
class Hm_Handler_default_page_data extends Hm_Handler_Module {
    /**
     * For now the data_sources array is the onl default
     */
    public function process() {
        $this->out('data_sources', array(), false);
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
        $this->out('is_mobile', $this->request->mobile);
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
        $list_path = '';
        $list_parent = '';
        $list_page = 1;
        $list_meta = true;
        $mailbox_list_title = array();
        $message_list_since = DEFAULT_SINCE;
        $per_source_limit = DEFAULT_PER_SOURCE;
        $no_message_list_headers = false;

        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if ($path == 'unread') {
                $list_path = 'unread';
                $mailbox_list_title = array('Unread');
                $message_list_since = $this->user_config->get('unread_since_setting', DEFAULT_SINCE);
                $per_source_limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
            }
            elseif ($path == 'email') {
                $message_list_since = $this->user_config->get('all_email_since_setting', DEFAULT_SINCE);
                $per_source_limit = $this->user_config->get('all_email_per_source_setting', DEFAULT_PER_SOURCE);
                $list_path = 'email';
                $mailbox_list_title = array('All Email');
            }
            elseif ($path == 'flagged') {
                $list_path = 'flagged';
                $message_list_since = $this->user_config->get('flagged_since_setting', DEFAULT_SINCE);
                $per_source_limit = $this->user_config->get('flagged_per_source_setting', DEFAULT_PER_SOURCE);
                $mailbox_list_title = array('Flagged');
            }
            elseif ($path == 'combined_inbox') {
                $list_path = 'combined_inbox';
                $message_list_since = $this->user_config->get('all_since_setting', DEFAULT_SINCE);
                $per_source_limit = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
                $mailbox_list_title = array('Everything');
            }
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
        $list_style = $this->user_config->get('list_style', false);
        if ($this->get('is_mobile', false)) {
            $list_style = 'news_style';
        }
        if ($list_style == 'news_style') {
            $no_message_list_headers = true;
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
        $this->out('no_message_list_headers', $no_message_list_headers);
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
 * Process search terms from a URL
 * @subpackage core/handler
 */
class Hm_Handler_process_search_terms extends Hm_Handler_Module {
    /**
     * validate and set search tems in the session
     */
    public function process() {
        if (array_key_exists('search_terms', $this->request->get)) {
            $this->out('run_search', 1);
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
        $this->out('search_fld', $this->session->get('search_fld', 'TEXT'));
    }
}

/**
 * Simple search form for the folder list
 * @subpackage core/output
 */
class Hm_Output_search_from_folder_list extends Hm_Output_Module {
    /**
     * Add a search form to the top of the folder list
     */
    protected function output() {
        $res = '<li class="menu_search"><form method="get"><a class="unread_link" href="?page=search">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$search).
            '" alt="" width="16" height="16" /></a><input type="hidden" name="page" value="search" />'.
            '<input type="search" class="search_terms" name="search_terms" placeholder="'.
            $this->trans('Search').'" /></form></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Start the search results content section
 * @subpackage core/output
 */
class Hm_Output_search_content_start extends Hm_Output_Module {
    /**
     * Leaves two open div tags that are closed in Hm_Output_search_content_end and Hm_Output_search_form
     */
    protected function output() {
        return '<div class="search_content"><div class="content_title">'.$this->trans('Search');
    }
}

/**
 * End of the search results content section
 * @subpackage core/output
 */
class Hm_Output_search_content_end extends Hm_Output_Module {
    /**
     * Closes a div opened in Hm_Output_search_content_start
     */
    protected function output() {
        return '</div>';
    }
}

/**
 * Output the search form used on the search page
 * @subpackage core/output
 */
class Hm_Output_search_form extends Hm_Output_Module {
    /**
     * Closes one of the divs left open in Hm_Output_search_content_start
     */
    protected function output() {
        $terms = $this->get('search_terms', '');
        $source_link = '<a href="#" title="Sources" class="source_link"><img alt="'.$this->trans('Sources').
            '" class="refresh_list" src="'.Hm_Image_Sources::$folder.'" width="20" height="20" /></a>';
        $refresh_link = '<a class="refresh_link" title="'.$this->trans('Refresh').'" href="#"><img alt="'.
            $this->trans('Refresh').'" class="refresh_list" src="'.Hm_Image_Sources::$refresh.'" width="20" height="20" /></a>';
        $res = '<div class="search_form">'.
            '<form method="get"><input type="hidden" name="page" value="search" />'.
            ' <label class="screen_reader" for="search_terms">'.$this->trans('Search Terms').'</label>'.
            '<input placeholder="'.$this->trans('Search Terms').'" id="search_terms" type="search" class="search_terms" name="search_terms" value="'.$this->html_safe($terms).'" />'.
            ' <label class="screen_reader" for="search_fld">'.$this->trans('Search Field').'</label>'.
            search_field_selection($this->get('search_fld', ''), $this).
            ' <label class="screen_reader" for="search_since">'.$this->trans('Search Since').'</label>'.
            message_since_dropdown($this->get('search_since', ''), 'search_since', $this).
            ' <input type="submit" class="search_update" value="'.$this->trans('Update').'" /></form></div>'.
            list_controls($refresh_link, false, $source_link).
            '</div>';
        return $res;
    }
}

/**
 * Closes the search results table
 * @subpackage core/output
 */
class Hm_Output_search_results_table_end extends Hm_Output_Module {
    /**
     */
    protected function output() {
        return '</tbody></table>';
    }
}

/**
 * Some inline JS used by the search page
 * @subpackage core/output
 */
class Hm_Output_js_search_data extends Hm_Output_Module {
    /**
     * adds two JS functions used on the search page
     */
    protected function output() {
        return '<script type="text/javascript">'.
            'var hm_search_terms = function() { return "'.$this->html_safe($this->get('search_terms', '')).'"; };'.
            'var hm_run_search = function() { return "'.$this->html_safe($this->get('run_search', 0)).'"; };'.
            '</script>';
    }
}

/**
 * Outputs the login or logout form
 * @subpackage core/output
 */
class Hm_Output_login extends Hm_Output_Module {
    /**
     * Looks at the current login state and outputs the correct form
     */
    protected function output() {
        if (!$this->get('router_login_state')) {
            return '<form class="login_form" method="POST">'.
                '<h1 class="title">'.$this->html_safe($this->get('router_app_name', '')).'</h1>'.
                ' <input type="hidden" name="hm_nonce" value="'.Hm_Nonce::site_key().'" />'.
                ' <label class="screen_reader" for="username">'.$this->trans('Username').'</label>'.
                '<input autofocus required type="text" placeholder="'.$this->trans('Username').'" id="username" name="username" value="">'.
                ' <label class="screen_reader" for="password">'.$this->trans('Password').'</label>'.
                '<input required type="password" id="password" placeholder="'.$this->trans('Password').'" name="password">'.
                ' <input type="submit" value="'.$this->trans('Login').'" /></form>';
        }
        else {
            return '<form class="logout_form" method="POST">'.
                '<input type="hidden" id="unsaved_changes" value="'.
                (!empty($this->get('changed_settings', array())) ? '1' : '0').'" />'.
                '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
                '<div class="confirm_logout"><div class="confirm_text">'.
                $this->trans('Unsaved changes will be lost! Re-neter your password to save and exit.').' &nbsp;'.
                '<a href="?page=save">'.$this->trans('More info').'</a></div>'.
                '<label class="screen_reader" for="logout_password">'.$this->trans('Password').'</label>'.
                '<input id="logout_password" name="password" class="save_settings_password" type="password" placeholder="'.$this->trans('Password').'" />'.
                '<input class="save_settings" type="submit" name="save_and_logout" value="'.$this->trans('Save and Logout').'" />'.
                '<input class="save_settings" id="logout_without_saving" type="submit" name="logout" value="'.$this->trans('Just Logout').'" />'.
                '<input class="cancel_logout save_settings" type="button" value="'.$this->trans('Cancel').'" />'.
                '</div></form>';
        }
    }
}

/**
 * Start the content section for the servers page
 * @subpackage core/output
 */
class Hm_Output_server_content_start extends Hm_Output_Module {
    /**
     * The server_content div is closed in Hm_Output_server_content_end
     */
    protected function output() {
        return '<div class="content_title">'.$this->trans('Servers').'</div><div class="server_content">';
    }
}

/**
 * Close the server content section
 * @subpackage core/output
 */
class Hm_Output_server_content_end extends Hm_Output_Module {
    /**
     * Closes the div tag opened in Hm_Output_server_content_start
     */
    protected function output() {
        return '</div>';
    }
}

/**
 * Output a date
 * @subpackage core/output
 */
class Hm_Output_date extends Hm_Output_Module {
    /**
     * Currently not show in the display (hidden with CSS)
     */
    protected function output() {
        return '<div class="date">'.$this->html_safe($this->get('date')).'</div>';
    }
}

/**
 * Display system messages
 * @subpackage core/output
 */
class Hm_Output_msgs extends Hm_Output_Module {
    /**
     * Error level messages start with ERR and will be shown in red
     */
    protected function output() {
        $res = '';
        $msgs = Hm_Msgs::get();
        $logged_out_class = '';
        if (!$this->get('router_login_state') && !empty($msgs)) {
            $logged_out_class = ' logged_out';
        }
        $res .= '<div class="sys_messages'.$logged_out_class.'">';
        if (!empty($msgs)) {
            $res .= implode(',', array_map(function($v) {
                if (preg_match("/ERR/", $v)) {
                    return sprintf('<span class="err">%s</span>', $this->trans(substr($v, 3)));
                }
                else {
                    return $this->trans($v);
                }
            }, $msgs));
        }
        $res .= '</div>';
        return $res;
    }
}

/**
 * Start the HTML5 document
 * @subpackage core/output
 */
class Hm_Output_header_start extends Hm_Output_Module {
    /**
     * Doctype, html, head, and a meta tag for charset. The head section is not closed yet
     */
    protected function output() {
        $lang = 'en';
        $dir = 'ltr';
        if ($this->lang) {
            $lang = strtolower(str_replace('_', '-', $this->lang));
        }
        if ($this->dir) {
            $dir = $this->dir;
        }
        $class = $dir."_page";
        return '<!DOCTYPE html><html dir="'.$this->html_safe($dir).'" class="'.
            $this->html_safe($class).'" lang='.$this->html_safe($lang).'><head><meta charset="utf-8" />';
    }
}

/**
 * Close the HTML5 head tag
 * @subpackage core/output
 */
class Hm_Output_header_end extends Hm_Output_Module {
    /**
     * Close the head tag opened in Hm_Output_header_start
     */
    protected function output() {
        return '</head>';
    }
}

/**
 * Start the content section
 * @subpackage core/output
 */
class Hm_Output_content_start extends Hm_Output_Module {
    /**
     * Outputs the starting body tag and a noscript warning. Clears the local session cache
     * if not logged in, or adds a page wide nonce used by ajax requests
     */
    protected function output() {
        $res = '<body><noscript class="noscript">'.
            $this->trans(sprintf('You Need to have Javascript enabled to use %s, sorry about that!',
            $this->html_safe($this->get('router_app_name')))).'</noscript>';
        if (!$this->get('router_login_state')) {
            $res .= '<script type="text/javascript">sessionStorage.clear();</script>';
        }
        else {
            $res .= '<input type="hidden" id="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />';
        }
        return $res;
    }
}

/**
 * Outputs HTML5 head tag content
 * @subpackage core/output
 */
class Hm_Output_header_content extends Hm_Output_Module {
    /**
     * Setup a title, base URL, a tab icon, and viewport settings
     */
    protected function output() {
        $title = '';
        if (!$this->get('router_login_state')) {
            $title = $this->get('router_app_name');
        }
        elseif ($this->exists('mailbox_list_title')) {
            $title .= ' '.implode('-', array_slice($this->get('mailbox_list_title', array()), 1));
        }
        if (!trim($title) && $this->exists('router_page_name')) {
            $title = '';
            if ($this->get('list_path') == 'message_list') {
                $title .= ' '.ucwords(str_replace('_', ' ', $this->get('list_path')));
            }
            elseif ($this->get('router_page_name') == 'notfound') {
                $title .= ' Nope';
            }
            else {
                $title .= ' '.ucfirst(str_replace('_', ' ', $this->get('router_page_name')));
            }
        }
        return '<title>'.$this->trans(trim($title)).'</title>'.
            '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">'.
            '<link rel="icon" class="tab_icon" type="image/png" href="'.Hm_Image_Sources::$env_closed.'">'.
            '<base href="'.$this->html_safe($this->get('router_url_path')).'" />';
    }
}

/**
 * Outputs CSS
 * @subpackage core/output
 */
class Hm_Output_header_css extends Hm_Output_Module {
    /**
     * In debug mode adds each module css file to the page, otherwise uses the combined version
     */
    protected function output() {
        $res = '';
        $mods = $this->get('router_module_list');
        if (DEBUG_MODE) {
            foreach (glob('modules/**', GLOB_ONLYDIR | GLOB_MARK) as $name) {
                $mod = str_replace(array('modules/', '/'), '', $name);
                if (stristr($mods, $mod) && is_readable(sprintf("%ssite.css", $name))) {
                    $res .= '<link href="'.sprintf("%ssite.css", $name).'" media="all" rel="stylesheet" type="text/css" />';
                }
            }
        }
        else {
            $res .= '<link href="site.css?v='.CACHE_ID.'" media="all" rel="stylesheet" type="text/css" />';
        }
        return $res;
    }
}

/**
 * Output JS
 * @subpackage core/output
 */
class Hm_Output_page_js extends Hm_Output_Module {
    /**
     * In debug mode adds each module js file to the page, otherwise uses the combined version.
     * Includes the zepto library.
     */
    protected function output() {
        if (DEBUG_MODE) {
            $res = '';
            $zepto = '<script type="text/javascript" src="third_party/zepto.min.js"></script>';
            $core = false;
            $mods = $this->get('router_module_list');
            foreach (glob('modules/**', GLOB_ONLYDIR | GLOB_MARK) as $name) {
                if ($name == 'modules/core/') {
                    $core = $name;
                    continue;
                }
                $mod = str_replace(array('modules/', '/'), '', $name);
                if (stristr($mods, $mod) && is_readable(sprintf("%ssite.js", $name))) {
                    $res .= '<script type="text/javascript" src="'.sprintf("%ssite.js", $name).'"></script>';
                }
            }
            if ($core) {
                $res = '<script type="text/javascript" src="'.sprintf("%ssite.js", $core).'"></script>'.$res;
            }
            return $zepto.$res;
        }
        else {
            return '<script type="text/javascript" src="site.js?v='.CACHE_ID.'"></script>';
        }
    }
}

/**
 * End the page content
 * @subpackage core/output
 */
class Hm_Output_content_end extends Hm_Output_Module {
    /**
     * Closes the body and html tags
     */
    protected function output() {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            return '<div class="debug"></div></body></html>';
        }
        else {
            return '</body></html>';
        }
    }
}

/**
 * Outputs page details for the JS
 * @subpackage core/output
 */
class Hm_Output_js_data extends Hm_Output_Module {
    /**
     * Uses function wrappers to make the data immutable from JS
     */
    protected function output() {
        return '<script type="text/javascript">'.
            'var hm_debug = function() { return "'.(DEBUG_MODE ? '1' : '0').'"; };'.
            'var hm_page_name = function() { return "'.$this->html_safe($this->get('router_page_name')).'"; };'.
            'var hm_list_path = function() { return "'.$this->html_safe($this->get('list_path', '')).'"; };'.
            'var hm_list_parent = function() { return "'.$this->html_safe($this->get('list_parent', '')).'"; };'.
            'var hm_msg_uid = function() { return "'.$this->html_safe($this->get('uid', '')).'"; };'.
            'var hm_data_sources = function() { return '.format_data_sources($this->get('data_sources', array()), $this).'; };'.
            '</script>';
    }
}

/**
 * Outputs a load icon
 * @subpackage core/output
 */
class Hm_Output_loading_icon extends Hm_Output_Module {
    /**
     * Sort of ugly loading icon animated with js/css
     */
    protected function output() {
        return '<div class="loading_icon"></div>';
    }
}

/**
 * Start the main form on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_settings_form extends Hm_Output_Module {
    /**
     * Opens a div, form and table
     */
    protected function output() {
        return '<div class="user_settings"><div class="content_title">'.$this->trans('Site Settings').'</div>'.
            '<form method="POST"><input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
            '<table class="settings_table"><colgroup>'.
            '<col class="label_col"><col class="setting_col"></colgroup>';
    }
}

/**
 * Outputs the list style option on the settings page
 * @subpackage core/output
 */
class Hm_Output_list_style_setting extends Hm_Output_Module {
    /**
     * Can be either "news style" or the default "email style"
     */
    protected function output() {
        $options = array('email_style' => 'Email', 'news_style' => 'News');
        $settings = $this->get('user_settings', array());

        if (array_key_exists('list_style', $settings)) {
            $list_style = $settings['list_style'];
        }
        else {
            $list_style = false;
        }
        $res = '<tr class="general_setting"><td><label for="list_style">'.
            $this->trans('Message list style').'</label></td>'.
            '<td><select id="list_style" name="list_style">';
        foreach ($options as $val => $label) {
            $res .= '<option ';
            if ($list_style == $val) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$val.'">'.$this->trans($label).'</option>';
        }
        $res .= '</select></td></tr>';
        return $res;
    }
}

/**
 * Starts the Flagged section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_flagged_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the flagged messages view
     */
    protected function output() {
        return '<tr><td data-target=".flagged_setting" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$star.'" width="16" height="16" />'.
            $this->trans('Flagged').'</td></tr>';
    }
}

/**
 * Start the Everything section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_everything_settings extends Hm_Output_Module {
    /**
     * Setttings in this section control the Everything view
     */
    protected function output() {
        return '<tr><td data-target=".all_setting" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$box.'" width="16" height="16" />'.
            $this->trans('Everything').'</td></tr>';
    }
}

/**
 * Start the Unread section on the settings page
 * @subpackage core/output
 */
class Hm_Output_start_unread_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the Unread view
     */
    protected function output() {
        return '<tr><td data-target=".unread_setting" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            $this->trans('Unread').'</td></tr>';
    }
}

/**
 * Start the E-mail section on the settings page.
 * @subpackage core/output
 */
class Hm_Output_start_all_email_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the All E-mail view. Skipped if there are no
     * E-mail enabled module sets.
     */
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        return '<tr><td data-target=".email_setting" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            $this->trans('All Email').'</td></tr>';
    }
}

/**
 * Start the general settings section
 * @subpackage core/output
 */
class Hm_Output_start_general_settings extends Hm_Output_Module {
    /**
     * General settings like langauge and timezone will go here
     */
    protected function output() {
        return '<tr><td data-target=".general_setting" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$cog.'" width="16" height="16" />'.
            $this->trans('General').'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Unread page
 * @subpackage core/output
 */
class Hm_Output_unread_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_unread_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('unread_per_source', $settings)) {
            $sources = $settings['unread_per_source'];
        }
        return '<tr class="unread_setting"><td><label for="unread_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td><input type="text" size="2" id="unread_per_source" name="unread_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

/**
 * Option for the "received since" date range for the Unread page
 * @subpackage core/output
 */
class Hm_Output_unread_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_unread_since_setting
     */
    protected function output() {
        $since = false;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('unread_since', $settings)) {
            $since = $settings['unread_since'];
        }
        return '<tr class="unread_setting"><td><label for="unread_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'unread_since', $this).'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Flagged page
 * @subpackage core/output
 */
class Hm_Output_flagged_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_flagged_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('flagged_per_source', $settings)) {
            $sources = $settings['flagged_per_source'];
        }
        return '<tr class="flagged_setting"><td><label for="flagged_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td><input type="text" size="2" id="flagged_per_source" name="flagged_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

/**
 * Option for the "received since" date range for the Flagged page
 * @subpackage core/output
 */
class Hm_Output_flagged_since_setting extends Hm_Output_Module {
    protected function output() {
        /**
         * Processed by Hm_Handler_process_flagged_since_setting
         */
        $since = false;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('flagged_since', $settings)) {
            $since = $settings['flagged_since'];
        }
        return '<tr class="flagged_setting"><td><label for="flagged_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'flagged_since', $this).'</td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the All E-mail  page
 * @subpackage core/output
 */
class Hm_Output_all_email_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_email_source_max_setting
     */
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        $sources = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('all_email_per_source', $settings)) {
            $sources = $settings['all_email_per_source'];
        }
        return '<tr class="email_setting"><td><label for="all_email_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td><input type="text" size="2" id="all_email_per_source" name="all_email_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the Everything page
 * @subpackage core/output
 */
class Hm_Output_all_source_max_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_source_max_setting
     */
    protected function output() {
        $sources = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('all_per_source', $settings)) {
            $sources = $settings['all_per_source'];
        }
        return '<tr class="all_setting"><td><label for="all_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td><input type="text" size="2" id="all_per_source" name="all_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

/**
 * Option for the "received since" date range for the All E-mail page
 * @subpackage core/output
 */
class Hm_Output_all_email_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_email_since_setting
     */
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        $since = false;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('all_email_since', $settings)) {
            $since = $settings['all_email_since'];
        }
        return '<tr class="email_setting"><td><label for="all_email_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'all_email_since', $this).'</td></tr>';
    }
}

/**
 * Option for the "received since" date range for the Everything page
 * @subpackage core/output
 */
class Hm_Output_all_since_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_all_since_setting
     */
    protected function output() {
        $since = false;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('all_since', $settings)) {
            $since = $settings['all_since'];
        }
        return '<tr class="all_setting"><td><label for="all_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'all_since', $this).'</td></tr>';
    }
}

/**
 * Option for the language setting
 * @subpackage core/output
 */
class Hm_Output_language_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_language_setting
     */
    protected function output() {
        $langs = interface_langs();
        $translated = array();
        foreach ($langs as $code => $name) {
            $translated[$code] = $this->trans($name);
        }
        asort($translated);
        $mylang = $this->get('language', '');
        $res = '<tr class="general_setting"><td><label for="language_setting">'.
            $this->trans('interface language').'</label></td>'.
            '<td><select id="language_setting" name="language_setting">';
        foreach ($translated as $id => $lang) {
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

/**
 * Option for the timezone setting
 * @subpackage core/output
 */
class Hm_Output_timezone_setting extends Hm_Output_Module {
    /**
     * Processed by Hm_Handler_process_timezone_setting
     */
    protected function output() {
        $zones = timezone_identifiers_list();
        $settings = $this->get('user_settings', array());
        if (array_key_exists('timezone', $settings)) {
            $myzone = $settings['timezone'];
        }
        else {
            $myzone = false;
        }
        $res = '<tr class="general_setting"><td><label for="timezone_setting">'.
            $this->trans('Timezone').'</label></td><td><select id="timezone_setting" name="timezone_setting">';
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

/**
 * Ends the settings table
 * @subpackage core/output
 */
class Hm_Output_end_settings_form extends Hm_Output_Module {
    /**
     * Closes the table, form and div opened in Hm_Output_start_settings_form
     */
    protected function output() {
        return '<tr><td class="submit_cell" colspan="2">'.
            '<input class="save_settings" type="submit" name="save_settings" value="'.$this->trans('Save').'" />'.
            '</td></tr></table></form></div>';
    }
}

/**
 * Start the folder list
 * @subpackage core/output
 */
class Hm_Output_folder_list_start extends Hm_Output_Module {
    /**
     * Opens the folder list nav tag
     */
    protected function output() {
        $res = '<a class="folder_toggle" href="#"><img alt="" src="'.Hm_Image_Sources::$big_caret.'" width="20" height="20" /></a>'.
            '<nav class="folder_cell"><div class="folder_list">';
        return $res;
    }
}

/**
 * Start the folder list content
 * @subpackage core/output
 */
class Hm_Output_folder_list_content_start extends Hm_Output_Module {
    /**
     * Creates a modfiable string called formatted_folder_list other modules append to
     */
    protected function output() {
        if ($this->format == 'HTML5') {
            return '';
        }
        $this->out('formatted_folder_list', '', false);
    }
}

/**
 * Start the Main menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_main_menu_start extends Hm_Output_Module {
    /**
     * Opens a div and unordered list tag
     */
    protected function output() {
        $res = '<div class="src_name main_menu" data-source=".main">'.$this->trans('Main').
        '<img alt="" class="menu_caret" src="'.Hm_Image_Sources::$chevron.'" width="8" height="8" />'.
        '</div><div class="main"><ul class="folders">';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Content for the Main menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_main_menu_content extends Hm_Output_Module {
    /**
     * Links to default pages: Home, Everything, Unread and Flagged
     * @todo break this up into smaller modules
     */
    protected function output() {
        $email = false;
        if (in_array('email_folders', $this->get('folder_sources', array()))) {
            $email = true;
        }
        $res = '<li class="menu_home"><a class="unread_link" href="?page=home">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$home).'" alt="" width="16" height="16" /> '.$this->trans('Home').'</a></li>'.
            '<li class="menu_combined_inbox"><a class="unread_link" href="?page=message_list&amp;list_path=combined_inbox">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$box).'" alt="" width="16" height="16" /> '.$this->trans('Everything').
            '</a><span class="combined_inbox_count"></span></li>';
        if ($email) {
            $res .= '<li class="menu_unread"><a class="unread_link" href="?page=message_list&amp;list_path=unread">'.
                '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$env_closed).'" alt="" width="16" height="16" /> '.$this->trans('Unread').'</a></li>';
        }
        $res .= '<li class="menu_flagged"><a class="unread_link" href="?page=message_list&amp;list_path=flagged">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$star).'" alt="" width="16" height="16" /> '.$this->trans('Flagged').
            '</a> <span class="flagged_count"></span></li>';

        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Outputs the logout link in the Main menu of the folder list
 * @subpackage core/output
 */
class Hm_Output_logout_menu_item extends Hm_Output_Module {
    protected function output() {
        $res =  '<li><a class="unread_link logout_link" href="#"><img class="account_icon" src="'.
            $this->html_safe(Hm_Image_Sources::$power).'" alt="" width="16" height="16" /> '.$this->trans('Logout').'</a></li>';

        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Close the Main menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_main_menu_end extends Hm_Output_Module {
    protected function output() {
        $res = '</ul></div>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Output the E-mail section of the folder list
 * @subpackage core/output
 */
class Hm_Output_email_menu_content extends Hm_Output_Module {
    /**
     * Displays a list of all configured E-mail accounts
     */
    protected function output() {
        $res = '';
        $folder_sources = array_unique($this->get('folder_sources', array()));
        foreach ($folder_sources as $src) {
            $parts = explode('_', $src);
            array_pop($parts);
            $name = ucwords(implode(' ', $parts));
            $res .= '<div class="src_name" data-source=".'.$this->html_safe($src).'">'.$this->trans($name).
                '<img class="menu_caret" src="'.Hm_Image_Sources::$chevron.'" alt="" width="8" height="8" /></div>';

            $res .= '<div style="display: none;" ';
            $res .= 'class="'.$this->html_safe($src).'"><ul class="folders">';
            if ($name == 'Email') {
                $res .= '<li class="menu_email"><a class="unread_link" href="?page=message_list&amp;list_path=email">'.
                    '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$globe).
                    '" alt="" width="16" height="16" /> '.$this->trans('All').'</a> <span class="unread_mail_count"></span></li>';
            }
            $cache = Hm_Page_Cache::get($src);
            Hm_Page_Cache::del($src);
            if ($cache) {
                $res .= $cache;
            }
            $res .= '</ul></div>';
        }
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Start the Settings section of the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_menu_start extends Hm_Output_Module {
    /**
     * Opens an unordered list
     */
    protected function output() {
        $res = '<div class="src_name" data-source=".settings">'.$this->trans('Settings').
            '<img class="menu_caret" src="'.Hm_Image_Sources::$chevron.'" alt="" width="8" height="8" />'.
            '</div><ul style="display: none;" class="settings folders">';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Save settings page content
 * @subpackage core/output
 */
class Hm_Output_save_form extends Hm_Output_Module {
    /**
     * Outputs save form
     */
    protected function output() {
        $changed = $this->get('changed_settings', array());
        $res = '<div class="save_settings_page"><div class="content_title">'.$this->trans('Save Settings').'</div>';
        $res .= '<div class="save_details">'.$this->trans('Settings are not saved permanently on the server unless you explicitly allow it. '.
            'If you don\'t save your settings, any changes made since you last logged in will be deleted when your '.
            'session expires or you logout. You must re-enter your password for security purposes to save your settings '.
            'permanently.');
        $res .= '<div class="save_subtitle">'.$this->trans('Unsaved Changes').'</div>';
        $res .= '<ul class="unsaved_settings">';
        if (!empty($changed)) {
            $changed = array_count_values($changed);
            foreach ($changed as $change => $num) {
                $res .= '<li>'.$this->trans($change).' ('.$this->html_safe($num).'X)</li>';
            }
        }
        else {
            $res .= '<li>'.$this->trans('No changes need to be saved').'</li>';
        }
        $res .= '</ul></div><div class="save_perm_form"><form method="post">'.
            '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
            '<label class="screen_reader" for="password">Password</label><input required id="password" '.
            'name="password" class="save_settings_password" type="password" placeholder="'.$this->trans('Password').'" />'.
            '<input class="save_settings" type="submit" name="save_settings_permanently" value="'.$this->trans('Save').'" />'.
            '<input class="save_settings" type="submit" name="save_settings_permanently_then_logout" value="'.$this->trans('Save and Logout').'" />'.
            '</form></div>';

        $res .= '</div>';
        return $res;
    }
}

/**
 * Content for the Settings menu section of the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_menu_content extends Hm_Output_Module {
    /**
     * Outputs links to the Servers and Site Settings pages
     */
    protected function output() {
        $res = '<li class="menu_servers"><a class="unread_link" href="?page=servers">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$monitor).
            '" alt="" width="16" height="16" /> '.$this->trans('Servers').'</a></li>'.
            '<li class="menu_settings"><a class="unread_link" href="?page=settings">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$cog).
            '" alt="" width="16" height="16" /> '.$this->trans('Site').'</a></li>'.
            '<li class="menu_save"><a class="unread_link" href="?page=save">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$save).
            '" alt="" width="16" height="16" /> '.$this->trans('Save').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Closes the Settings menu in the folder list
 * @subpackage core/output
 */
class Hm_Output_settings_menu_end extends Hm_Output_Module {
    /**
     * Closes the ul tag opened in Hm_Output_settings_menu_start
     */
    protected function output() {
        $res = '</ul>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * End of the content section of the folder list
 * @subpackage core/output
 */
class Hm_Output_folder_list_content_end extends Hm_Output_Module {
    /**
     * Adds collapse and reload links
     */
    protected function output() {
        $res = '<a href="#" class="update_message_list">'.$this->trans('[reload]').'</a>';
        $res .= '<a href="#" class="hide_folders"><img src="'.Hm_Image_Sources::$big_caret_left.
            '" alt="'.$this->trans('Collapse').'" width="16" height="16" /></a>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * Closes the folder list
 * @subpackage core/output
 */
class Hm_Output_folder_list_end extends Hm_Output_Module {
    /**
     * Closes the div and nav tags opened in Hm_Output_folder_list_start
     */
    protected function output() {
        return '</div></nav>';
    }
}

/**
 * Starts the main content section
 * @subpackage core/output
 */
class Hm_Output_content_section_start extends Hm_Output_Module {
    /**
     * Opens a main tag for the primary content section
     */
    protected function output() {
        return '<main class="content_cell">';
    }
}

/**
 * Closes the main content section
 * @subpackage core/output
 */
class Hm_Output_content_section_end extends Hm_Output_Module {
    /**
     * Closes the main tag opened in Hm_Output_content_section_start
     */
    protected function output() {
        return '</main>';
    }
}

/**
 * Starts the message view page
 * @subpackage core/output
 */
class Hm_Output_message_start extends Hm_Output_Module {
    /**
     * @todo this is pretty ugly, clean it up and remove module specific stuff
     */
    protected function output() {
        if ($this->in('list_parent', array('search', 'flagged', 'combined_inbox', 'unread', 'feeds', 'email'))) {
            if ($this->get('list_parent') == 'combined_inbox') {
                $list_name = $this->trans('Everything');
            }
            elseif ($this->get('list_parent') == 'email') {
                $list_name = $this->trans('All Email');
            }
            else {
                $list_name = $this->trans(ucwords(str_replace('_', ' ', $this->get('list_parent', ''))));
            }
            if ($this->get('list_parent') == 'search') {
                $page = 'search';
            }
            else {
                $page = 'message_list';
            }
            $title = '<a href="?page='.$page.'&amp;list_path='.$this->html_safe($this->get('list_parent')).'">'.$list_name.'</a>';
            if (count($this->get('mailbox_list_title', array())) > 1) {
                $mb_title = $this->get('mailbox_list_title', array());
                $title .= '<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" />'.
                    '<a href="?page='.$page.'&amp;list_path='.$this->html_safe($this->get('list_path')).'">'.$this->trans($mb_title[1]).'</a>';
            }
        }
        elseif ($this->get('mailbox_list_title')) {
            $title = '<a href="?page=message_list&amp;list_path='.$this->html_safe($this->get('list_path')).'">'.
                implode('<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" />',
                    array_map( function($v) { return $this->trans($v); }, $this->get('mailbox_list_title', array()))).'</a>';
        }
        else {
            $title = '';
        }
        $res = '';
        if ($this->get('uid')) {
            $res .= '<input type="hidden" class="msg_uid" value="'.$this->html_safe($this->get('uid')).'" />';
        }
        $res .= '<div class="content_title">'.$title.'</div>';
        $res .= '<div class="msg_text">';
        return $res;
    }
}

/**
 * Close the message view content section
 * @subpackage core/output
 */
class Hm_Output_message_end extends Hm_Output_Module {
    /**
     * Closes the div opened in Hm_Output_message_start
     */
    protected function output() {
        return '</div>';
    }
}

/**
 * Not found content for a bad page request
 * @subpackage core/output
 */
class Hm_Output_notfound_content extends Hm_Output_Module {
    /**
     * Simple "page not found" page content
     */
    protected function output() {
        $res = '<div class="content_title">'.$this->trans('Page Not Found!').'</div>';
        $res .= '<div class="empty_list"><br />'.$this->trans('Nothingness').'</div>';
        return $res;
    }
}

/**
 * Start the table for a message list
 * @subpackage core/output
 */
class Hm_Output_message_list_start extends Hm_Output_Module {
    /**
     * Uses the message_list_fields input to determine the format.
     */
    protected function output() {

        $col_flds = array();
        $header_flds = array();
        foreach ($this->get('message_list_fields', array()) as $vals) {
            if ($vals[0]) {
                $col_flds[] = sprintf('<col class="%s">', $vals[0]);
            }
            if ($vals[1] && $vals[2]) {
                $header_flds[] = sprintf('<th class="%s">%s</th>', $vals[1], $this->trans($vals[2]));
            }
            else {
                $header_flds[] = '<th></th>';
            }
        }
        $res = '<table class="message_table">';
        if (!$this->get('no_message_list_headers')) {
            if (!empty($col_flds)) {
                $res .= '<colgroup>'.implode('', $col_flds).'</colgroup>';
            }
            if (!empty($header_flds)) {
                $res .= '<thead><tr>'.implode('', $header_flds).'</tr></thead>';
            }
        }
        $res .= '<tbody>';
        return $res;
    }
}

/**
 * Output the heading for the home page
 * @subpackage core/output
 */
class Hm_Output_home_heading extends Hm_Output_Module {
    /**
     */
    protected function output() {
        return '<div class="content_title">'.$this->trans('Home').'</div>';
    }
}
/**
 * Output the heading for a message list
 * @subpackage core/output
 */
class Hm_Output_message_list_heading extends Hm_Output_Module {
    /**
     * @todo remove modle specific stuff
     */
    protected function output() {
        if ($this->in('list_path', array('unread', 'flagged', 'pop3', 'combined_inbox', 'feeds', 'email'))) {
            $source_link = '<a href="#" title="'.$this->trans('Sources').'" class="source_link"><img alt="Sources" class="refresh_list" src="'.Hm_Image_Sources::$folder.'" width="20" height="20" /></a>';
            if ($this->get('list_path') == 'combined_inbox') {
                $path = 'all';
            }
            else {
                $path = $this->get('list_path');
            }
            $config_link = '<a title="'.$this->trans('Configure').'" href="?page=settings#'.$path.'_setting"><img alt="Configure" class="refresh_list" src="'.Hm_Image_Sources::$cog.'" width="20" height="20" /></a>';
            $refresh_link = '<a class="refresh_link" title="'.$this->trans('Refresh').'" href="#"><img alt="Refresh" class="refresh_list" src="'.Hm_Image_Sources::$refresh.'" width="20" height="20" /></a>';

        }
        else {
            $config_link = '';
            $source_link = '';
            $refresh_link = '';
        }
        $res = '';
        $res .= '<div class="message_list"><div class="content_title">';
        $res .= message_controls($this).
            implode('<img class="path_delim" src="'.Hm_Image_Sources::$caret.'" alt="&gt;" width="8" height="8" />', array_map( function($v) { return $this->html_safe($v); },
                $this->get('mailbox_list_title', array())));
        $res .= list_controls($refresh_link, $config_link, $source_link);
	    $res .= message_list_meta($this->module_output(), $this);
        $res .= list_sources($this->get('data_sources', array()), $this);
        $res .= '</div>';
        return $res;
    }
}

/**
 * End a message list table
 * @subpackage core/output
 */
class Hm_Output_message_list_end extends Hm_Output_Module {
    /**
     * Close the table opened in Hm_Output_message_list_start
     */
    protected function output() {
        $res = '</tbody></table><div class="page_links"></div></div>';
        return $res;
    }
}

?>
