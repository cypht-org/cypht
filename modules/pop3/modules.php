<?php

/**
 * POP3 modules
 * @package modules
 * @subpackage pop3
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/pop3/hm-pop3.php';

/**
 * Setup the list type
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_message_list_type extends Hm_Handler_Module {
    /**
     * Build meta information about the message list type
     */
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if (preg_match("/^pop3_(\d+)$/", $path, $matches)) {
                $this->out('list_path', $path);
                $details = Hm_POP3_List::dump($matches[1]);
                if (!empty($details)) {
                    if ($details['name'] == 'Default-Auth-Server') {
                        $details['name'] = 'Default';
                    }
                    $this->out('mailbox_list_title', array('POP3', $details['name'], 'INBOX'));
                    $this->out('message_list_since', $this->user_config->get('pop3_since', DEFAULT_SINCE));
                    $this->out('per_source_limit', $this->user_config->get('pop3_limit', DEFAULT_SINCE));
                }
            }
        }
        if (array_key_exists('page', $this->request->get) && $this->request->get['page'] == 'search') {
            $this->out('list_path', 'search');
        }
    }
}

/**
 * Process the setting for the max number of POP3 messages per server
 * @subpackage pop3/handler
 */
class Hm_Handler_process_pop3_limit_setting extends Hm_Handler_Module {
    /**
     * Called when submitting settings
     */
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'pop3_limit'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if ($form['pop3_limit'] > MAX_PER_SOURCE || $form['pop3_limit'] < 0) {
                $limit = DEFAULT_PER_SOURCE;
            }
            else {
                $limit = $form['pop3_limit'];
            }
            $new_settings['pop3_limit'] = $limit;
        }
        else {
            $settings['pop3_limit'] = $this->user_config->get('pop3_limit', DEFAULT_PER_SOURCE);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Process the message since setting per POP3 account
 * @subpackage pop3/handler
 */
class Hm_Handler_process_pop3_since_setting extends Hm_Handler_Module {
    /**
     * Called when submitting settings
     */
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'pop3_since'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['pop3_since'] = process_since_argument($form['pop3_since'], true);
        }
        else {
            $settings['pop3_since'] = $this->user_config->get('pop3_since', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

/**
 * Check the status of a POP3 connection
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_status extends Hm_Handler_Module {
    /**
     * Used in an ajax request on the home page to determine a POP3 server status
     */
    public function process() {
        list($success, $form) = $this->process_form(array('pop3_server_ids'));
        if ($success) {
            $ids = explode(',', $form['pop3_server_ids']);
            foreach ($ids as $id) {
                $start_time = microtime(true);
                $pop3 = Hm_POP3_List::connect($id, false);
                if ($pop3->state == 'authed') {
                    $this->out('pop3_connect_time', microtime(true) - $start_time);
                    $this->out('pop3_connect_status', 'Authenticated');
                    $this->out('pop3_status_server_id', $id);
                }
            }
        }
    }
}

/**
 * Perform a message action on a POP3 message
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_message_action extends Hm_Handler_Module {
    /**
     * read or unread a POP3 message
     * @todo add support for more message actions
     */
    public function process() {
        list($success, $form) = $this->process_form(array('action_type', 'message_ids'));
        if ($success) {
            $id_list = explode(',', $form['message_ids']);
            foreach ($id_list as $msg_id) {
                if (preg_match("/^pop3_(\d)+_(\d)+$/", $msg_id)) {
                    switch($form['action_type']) {
                        case 'unread':
                            Hm_POP3_Uid_Cache::unread($msg_id);
                            break;
                        case 'read':
                            Hm_POP3_Uid_Cache::read($msg_id);
                            break;
                    }
                }
            }
        }
    }
}

/**
 * Build the data for a POP3 folder page
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_folder_page extends Hm_Handler_Module {
    /**
     * Connect to a POP3 server and fetch message headers
     * @todo see if this can be broken up into smaller functions
     */
    public function process() {

        $msgs = array();
        list($success, $form) = $this->process_form(array('pop3_server_id'));
        if ($success) {
            $unread_only = false;
            $login_time = $this->session->get('login_time', false);
            if ($login_time) {
                $this->out('login_time', $login_time);
            }
            $terms = false;
            if (array_key_exists('pop3_search', $this->request->post)) {
                $limit = $this->user_config->get('pop3_limit', DEFAULT_PER_SOURCE);
                $terms = $this->session->get('search_terms', false);
                $since = $this->session->get('search_since', DEFAULT_SINCE);
                $fld = $this->session->get('search_fld', 'TEXT');
                $date = process_since_argument($since);
                $cutoff_timestamp = strtotime($date);
            }
            elseif ($this->get('list_path') == 'unread') {
                $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE));
                $unread_only = true;
                $cutoff_timestamp = strtotime($date);
                if ($login_time && $login_time > $cutoff_timestamp) {
                    $cutoff_timestamp = $login_time;
                }
            }
            elseif ($this->get('list_path') == 'email') {
                $limit = $this->user_config->get('all_email_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_email_since_setting', DEFAULT_SINCE));
                $cutoff_timestamp = strtotime($date);
            }
            elseif ($this->get('list_path') == 'combined_inbox') {
                $limit = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
                $cutoff_timestamp = strtotime($date);
            }
            else {
                $limit = $this->user_config->get('pop3_limit', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('pop3_since', DEFAULT_SINCE));
                $cutoff_timestamp = strtotime($date);
            }
            $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false);
            $details = Hm_POP3_List::dump($form['pop3_server_id']);
            $path = sprintf("pop3_%d", $form['pop3_server_id']);
            if ($pop3->state == 'authed') {
                $this->out('pop3_mailbox_page_path', $path);
                $list = array_slice(array_reverse(array_unique(array_keys($pop3->mlist()))), 0, $limit);
                foreach ($list as $id) {
                    $msg_headers = $pop3->msg_headers($id);
                    if (!empty($msg_headers)) {
                        if (isset($msg_headers['date'])) {
                            if (!Hm_POP3_Uid_Cache::is_unread(sprintf('pop3_%d_%d', $form['pop3_server_id'], $id)) && strtotime($msg_headers['date']) < $cutoff_timestamp) {
                                continue;
                            }
                        }
                        if ($unread_only && Hm_POP3_Uid_Cache::is_read(sprintf('pop3_%d_%d', $form['pop3_server_id'], $id))) {
                            continue;
                        }
                        if ($terms) {
                            $body = implode('', $pop3->retr_full($id));
                            if (!search_pop3_msg($body, $msg_headers, $terms, $fld)) {
                                continue;
                            }
                        }
                        $msg_headers['server_name'] = $details['name'];
                        $msg_headers['server_id'] = $form['pop3_server_id'];
                        $msgs[$id] = $msg_headers;
                    }
                }
                $this->out('pop3_mailbox_page', $msgs);
                $this->out('pop3_server_id', $form['pop3_server_id']);
            }
        }
    }
}

/**
 * Fetch a message from a POP3 server
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_message_content extends Hm_Handler_Module {
    /**
     * Connect to a POP3 server and download a message
     */
    public function process() {

        list($success, $form) = $this->process_form(array('pop3_uid', 'pop3_list_path'));
        if ($success) {
            $id = (int) substr($form['pop3_list_path'], 4);
            $pop3 = Hm_POP3_List::connect($id, false);
            $details = Hm_POP3_List::dump($id);
            if ($pop3->state == 'authed') {
                $msg_lines = $pop3->retr_full($form['pop3_uid']);
                $header_list = array();
                $body = array();
                $headers = true;
                $last_header = false;
                foreach ($msg_lines as $line) {
                    if ($headers) {
                        if (substr($line, 0, 1) == "\t") {
                            $header_list[$last_header] .= ' '.trim($line);
                        }
                        elseif (strstr($line, ':')) {
                            $parts = explode(':', $line, 2);
                            if (count($parts) == 2) {
                                $header_list[$parts[0]] = trim($parts[1]);
                                $last_header = $parts[0];
                            }
                        }
                    }
                    else {
                        $body[] = $line;
                    }
                    if (!trim($line)) {
                        $headers = false;
                    }
                }
                $this->out('pop3_message_headers', $header_list);
                $this->out('pop3_message_body', $body);
                $this->out('pop3_mailbox_page_path', $form['pop3_list_path']);
                $this->out('pop3_server_id', $id);

                Hm_POP3_Uid_Cache::read(sprintf("pop3_%s_%s", $id, $form['pop3_uid']));
            }
        }
    }
}

/**
 * Save a POP3 server on the servers page
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_save extends Hm_Handler_Module {
    /**
     * Authenticate and save a POP3 server on the settings page
     */
    public function process() {
        $just_saved_credentials = false;
        if (isset($this->request->post['pop3_save'])) {
            list($success, $form) = $this->process_form(array('pop3_user', 'pop3_pass', 'pop3_server_id'));
            if (!$success) {
                Hm_Msgs::add('ERRUsername and Password are required to save a connection');
            }
            else {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false, $form['pop3_user'], $form['pop3_pass'], true);
                if ($pop3->state == 'authed') {
                    $just_saved_credentials = true;
                    Hm_Msgs::add("Server saved");
                    $this->session->record_unsaved('POP3 server saved');
                }
                else {
                    Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
                }
            }
        }
        $this->out('just_saved_credentials', $just_saved_credentials);
    }
}

/**
 * Forget the username and password for a POP3 server
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_forget extends Hm_Handler_Module {
    /**
     * Used on the settings page to forget the username/password of a POP3 server
     */
    public function process() {
        $just_forgot_credentials = false;
        if (isset($this->request->post['pop3_forget'])) {
            list($success, $form) = $this->process_form(array('pop3_server_id'));
            if ($success) {
                Hm_POP3_List::forget_credentials($form['pop3_server_id']);
                $just_forgot_credentials = true;
                Hm_Msgs::add('Server credentials forgotten');
                $this->session->record_unsaved('POP3 server credentials forgotten');
            }
            else {
                $this->out('old_form', $form);
            }
        }
        $this->out('just_forgot_credentials', $just_forgot_credentials);
    }
}

/**
 * Delete a POP3 server from the settings page
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_delete extends Hm_Handler_Module {
    /**
     * Delete a POP3 server
     */
    public function process() {
        if (isset($this->request->post['pop3_delete'])) {
            list($success, $form) = $this->process_form(array('pop3_server_id'));
            if ($success) {
                $res = Hm_POP3_List::del($form['pop3_server_id']);
                if ($res) {
                    $this->out('deleted_server_id', $form['pop3_server_id']);
                    Hm_Msgs::add('Server deleted');
                    $this->session->record_unsaved('POP3 server deleted');
                }
            }
            else {
                $this->out('old_form', $form);
            }
        }
    }
}

/**
 * Test a connection to a POP3 server
 * @subpackage pop3/handler
 */
class Hm_Handler_pop3_connect extends Hm_Handler_Module {
    /**
     * Used on the servers page to test a POP3 connection
     */
    public function process() {
        $pop3 = false;
        if (isset($this->request->post['pop3_connect'])) {
            list($success, $form) = $this->process_form(array('pop3_user', 'pop3_pass', 'pop3_server_id'));
            if ($success) {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false, $form['pop3_user'], $form['pop3_pass']);
            }
            elseif (isset($form['pop3_server_id'])) {
                $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false);
            }
            if ($pop3 && $pop3->state == 'authed') {
                Hm_Msgs::add("Successfully authenticated to the POP3 server");
            }
            else {
                Hm_Msgs::add("ERRFailed to authenticate to the POP3 server");
            }
        }
    }
}

/**
 * Load a POP3 cache
 * @subpackage pop3/handler
 */
class Hm_Handler_load_pop3_cache extends Hm_Handler_Module {
    /**
     * Load POP3 cache data from the session
     * @todo finish this
     */
    public function process() {
        $servers = Hm_POP3_List::dump();
        $cache = $this->session->get('pop3_cache', array()); 
        foreach ($servers as $index => $server) {
            if (isset($cache[$index])) {
            }
        }
    }
}

/**
 * Save a POP3 server cache
 * @subpackage pop3/handler
 */
class Hm_Handler_save_pop3_cache extends Hm_Handler_Module {
    /**
     * Save a POP3 server cache in the session
     * @todo finish this
     *
     */
    public function process() {
    }
}

/**
 * Load POP3 servers up for the search page
 * @subpackage pop3/handler
 */
class Hm_Handler_load_pop3_servers_for_search extends Hm_Handler_Module {
    /**
     * Add POP3 servers to the data sources list for the search page
     */
    public function process() {
        foreach (Hm_POP3_List::dump() as $index => $vals) {
            $this->append('data_sources', array('callback' => 'pop3_search_page_content', 'type' => 'pop3', 'name' => $vals['name'], 'id' => $index));
        }
    }
}

/**
 * Load POP3 server for combined message views
 * @subpackage pop3/handler
 */
class Hm_Handler_load_pop3_servers_for_message_list extends Hm_Handler_Module {
    /**
     * Load POP3 servers for a combined message list
     */
    public function process() {
        $server_id = false;
        $callback = false;
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
        }
        else {
            $path = '';
        }
        switch ($path) {
            case 'unread':
                $callback = 'pop3_combined_unread_content';
                break;
            case 'combined_inbox':
                $callback = 'pop3_combined_inbox_content';
                break;
            case 'email':
                $callback = 'pop3_all_mail_content';
                break;
            default:
                if (preg_match("/^pop3_(\d+)$/", $path, $matches)) {
                    $server_id = $matches[1];
                    $callback = 'load_pop3_list';
                }
                break;
        }
        if ($callback) {
            foreach (Hm_POP3_List::dump() as $index => $vals) {
                if ($server_id !== false && $server_id != $index) {
                    continue;
                }
                $this->append('data_sources', array('callback' => $callback, 'type' => 'pop3', 'name' => $vals['name'], 'id' => $index));
            }
        }
    }
}

/**
 * Load POP3 servers from the user config
 * @subpackage pop3/handler
 */
class Hm_Handler_load_pop3_servers_from_config extends Hm_Handler_Module {
    /**
     * Setup the POP3 server list
     */
    public function process() {
        $servers = $this->user_config->get('pop3_servers', array());
        $added = false;
        foreach ($servers as $index => $server) {
            Hm_POP3_List::add( $server, $index );
            if ($server['name'] == 'Default-Auth-Server') {
                $added = true;
            }
        }
        if (!$added) {
            $auth_server = $this->session->get('pop3_auth_server_settings', array());
            if (!empty($auth_server)) {
                Hm_POP3_List::add(array( 
                    'name' => 'Default-Auth-Server',
                    'server' => $auth_server['server'],
                    'port' => $auth_server['port'],
                    'tls' => $auth_server['tls'],
                    'user' => $auth_server['username'],
                    'pass' => $auth_server['password']),
                count($servers));
                $this->session->del('pop3_auth_server_settings');
            }
        }
        Hm_POP3_Uid_Cache::load($this->session->get('pop3_read_uids', array()));
    }
}

/**
 * Add a new POP3 server
 * @subpackage pop3/handler
 */
class Hm_Handler_process_add_pop3_server extends Hm_Handler_Module {
    /**
     * Used on the servers page to process adding a new POP3 server
     */
    public function process() {
        if (isset($this->request->post['submit_pop3_server'])) {
            list($success, $form) = $this->process_form(array('new_pop3_name', 'new_pop3_address', 'new_pop3_port'));
            if (!$success) {
                $this->out('old_form', $form);
                Hm_Msgs::add('ERRYou must supply a name, a server and a port');
            }
            else {
                $tls = false;
                if (isset($this->request->post['tls'])) {
                    $tls = true;
                }
                if ($con = fsockopen($form['new_pop3_address'], $form['new_pop3_port'], $errno, $errstr, 2)) {
                    Hm_POP3_List::add( array(
                        'name' => $form['new_pop3_name'],
                        'server' => $form['new_pop3_address'],
                        'port' => $form['new_pop3_port'],
                        'tls' => $tls));
                    Hm_Msgs::add('Added server!');
                    $this->session->record_unsaved('POP3 server added');
                }
                else {
                    Hm_Msgs::add(sprintf('ERRCound not add server: %s', $errstr));
                }
            }
        }
    }
}

/**
 * Add a list of POP3 servers for the output modules
 * @subpackage pop3/handler
 */
class Hm_Handler_add_pop3_servers_to_page_data extends Hm_Handler_Module {
    /**
     * Used to add POP3 server ids to an output page
     */
    public function process() {
        $servers = Hm_POP3_List::dump();
        $this->out('pop3_servers', $servers);
        if (!empty($servers)) {
            $this->append('email_folders', 'folder_sources');
        }
    }
}

/**
 * Load POP3 folder data used by the folder list
 * @subpackage pop3/handler
 */
class Hm_Handler_load_pop3_folders extends Hm_Handler_Module {
    /**
     * Add POP3 folders to the folder list E-mail section
     */
    public function process() {
        $servers = Hm_POP3_List::dump();
        $folders = array();
        if (!empty($servers)) {
            foreach ($servers as $id => $server) {
                if ($server['name'] == 'Default-Auth-Server') {
                    $server['name'] = 'Default';
                }
                $folders[$id] = $server['name'];
            }
        }
        $this->out('pop3_folders', $folders);
    }
}

/**
 * Save POP3 server list
 * @subpackage pop3/handler
 */
class Hm_Handler_save_pop3_servers extends Hm_Handler_Module {
    /**
     * Save POP3 servers in the session
     */
    public function process() {
        $servers = Hm_POP3_List::dump(false, true);
        $this->user_config->set('pop3_servers', $servers);
        $this->session->set('pop3_read_uids', Hm_POP3_Uid_Cache::dump());
        Hm_POP3_List::clean_up();
    }
}

/**
 * Format the add POP3 server dialog
 * @subpackage pop3/output
 */
class Hm_Output_add_pop3_server_dialog extends Hm_Output_Module {
    /**
     * Build the HTML for the add server dialog
     */
    protected function output() {
        $count = count($this->get('pop3_servers', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="pop3_server_setup"><div data-target=".pop3_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            ' '.$this->trans('POP3 Servers').' <div class="server_count">'.$count.'</div></div><div class="pop3_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="subtitle">'.$this->trans('Add a POP3 Server').'</div>'.
            '<table><tr><td colspan="2"><label class="screen_reader" for="new_pop3_name">'.$this->trans('POP3 account name').'</label>'.
            '<input required type="text" id="new_pop3_name" name="new_pop3_name" class="txt_fld" value="" placeholder="'.$this->trans('Account name').'" /></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_pop3_address">'.$this->trans('POP3 server address').'</label>'.
            '<input required type="text" id="new_pop3_address" name="new_pop3_address" class="txt_fld" placeholder="'.$this->trans('POP3 server address').'" value=""/></td></tr>'.
            '<tr><td colspan="2"><label for="new_pop3_port" class="screen_reader">'.$this->trans('POP3 port').'</label>'.
            '<input required type="number" id="new_pop3_port" name="new_pop3_port" class="port_fld" value="" placeholder="'.$this->trans('Port').'"></td></tr>'.
            '<tr><td><input type="checkbox" name="tls" value="1" id="pop3_tls" checked="checked" /> <label for="pop3_tls">'.$this->trans('Use TLS').'</label></td>'.
            '<td><input type="submit" value="'.$this->trans('Add').'" name="submit_pop3_server" /></td></tr>'.
            '</table></form>';
    }
}

/**
 * Format a list of configured POP3 servers
 * @subpackage pop3/output
 */
class Hm_Output_display_configured_pop3_servers extends Hm_Output_Module {
    /**
     * Build HTML for configured POP3 servers on the servers page
     */
    protected function output() {
        $res = '';
        foreach ($this->get('pop3_servers', array()) as $index => $vals) {

            $no_edit = false;

            if (isset($vals['user'])) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            if ($vals['name'] == 'Default-Auth-Server') {
                $vals['name'] = $this->trans('Default');
                $no_edit = true;
            }
            $res .= '<div class="configured_server">';
            $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']), $vals['tls'] ? 'TLS' : '' );
            $res .= 
                '<form class="pop3_connect" method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<input type="hidden" name="pop3_server_id" value="'.$this->html_safe($index).'" /><span> '.
                '<label class="screen_reader" for="pop3_user_'.$index.'">'.$this->trans('POP3 username').'</label>'.
                '<input '.$disabled.' id="pop3_user_'.$index.'" class="credentials" placeholder="'.$this->trans('Username').'" type="text" name="pop3_user" value="'.$user_pc.'"></span>'.
                '<span> <label class="screen_reader" for="pop3_password_'.$index.'">'.$this->trans('POP3 password').'</label>'.
                '<input '.$disabled.' id="pop3_password_'.$index.'" class="credentials pop3_password" placeholder="'.$pass_pc.'" type="password" name="pop3_pass"></span>';
            if (!$no_edit) {
                $res .= '<input type="submit" value="Test" class="test_pop3_connect" />';
                if (!isset($vals['user']) || !$vals['user']) {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_pop3_connection" />';
                    $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_pop3_connection" />';
                }
                else {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_pop3_connection" />';
                    $res .= '<input type="submit" value="'.$this->trans('Forget').'" class="forget_pop3_connection" />';
                }
                $res .= '<input type="hidden" value="ajax_pop3_debug" name="hm_ajax_hook" />';
            }
            $res .= '</form></div>';
        }
        $res .= '<br class="clear_float" /></div></div>';
        return $res;
    }
}

/**
 * Format POP3 folders for the folder list
 * @subpackage pop3/output
 */
class Hm_Output_filter_pop3_folders extends Hm_Output_Module {
    /**
     * Build the HTML for POP3 accounts in the folder list
     */
    protected function output() {
        $res = '';
        foreach ($this->get('pop3_folders', array()) as $id => $folder) {
            $res .= '<li class="pop3_'.$this->html_safe($id).'">'.
                '<a data-id="pop3_'.$this->html_safe($id).'" href="?page=message_list&list_path=pop3_'.$this->html_safe($id).'">'.
                '<img class="account_icon" alt="Toggle folder" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> '.
                $this->html_safe($folder).'</a></li>';
        }
        Hm_Page_Cache::concat('email_folders', $res);
        return '';
    }
}

/**
 * Format a POP3 message for display
 * @subpackage pop3/output
 */
class Hm_Output_filter_pop3_message_content extends Hm_Output_Module {
    /**
     * Build the HTML for a POP3 message view
     */
    protected function output() {
        if ($this->get('pop3_message_headers')) {
            $txt = '';
            $from = '';
            $small_headers = array('subject', 'date', 'from');
            $headers = $this->get('pop3_message_headers');
            $txt .= '<table class="msg_headers">'.
                '<col class="header_name_col"><col class="header_val_col"></colgroup>';
            foreach ($small_headers as $fld) {
                foreach ($headers as $name => $value) {
                    if ($fld == strtolower($name)) {
                        if ($fld == 'from') {
                            $from = $value;
                        }
                        if ($fld == 'subject') {
                            $txt .= '<tr class="header_'.$fld.'"><th colspan="2">';
                            if (isset($headers['Flags']) && stristr($headers['Flags'], 'flagged')) {
                                $txt .= ' <img alt="" class="account_icon" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> ';
                            }
                            $txt .= $this->html_safe($value).'</th></tr>';
                        }
                        else {
                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                        }
                        break;
                    }
                }
            }
            foreach ($headers as $name => $value) {
                if (!in_array(strtolower($name), $small_headers)) {
                    $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                }
            }
            $txt .= '<tr><th colspan="2" class="header_links">'.
                '<a href="#" class="header_toggle">'.$this->trans('all').'</a>'.
                '<a class="header_toggle" style="display: none;" href="#">small</a>'.
                ' | <a href="?page=compose">'.$this->trans('reply').'</a>'.
                ' | <a href="?page=compose">'.$this->trans('forward').'</a>'.
                ' | <a href="?page=compose">'.$this->trans('attach').'</a>'.
                ' | <a data-message-part="0" href="#">'.$this->trans('raw').'</a>'.
                ' | <a href="#">'.$this->trans('flag').'</a>'.
                '</th></tr></table>';

            $this->out('msg_headers', $txt);
        }
        $txt = '<div class="msg_text_inner">';
        if ($this->get('pop3_message_body')) {
            $txt .= format_msg_text(implode('', $this->get('pop3_message_body')), $this);
        }
        $txt .= '</div>';
        $this->out('msg_text', $txt);
    }
}

/**
 * Format a list of POP3 messages
 * @subpackage pop3/output
 */
class Hm_Output_filter_pop3_message_list extends Hm_Output_Module {
    /**
     * Build the HTML for a set of POP3 messages
     */
    protected function output() {
        $formatted_message_list = array();
        if ($this->get('pop3_mailbox_page')) {
            $style = $this->get('news_list_style') ? 'news' : 'email';
            if ($this->get('is_mobile')) {
                $style = 'news';
            }
            if ($this->get('login_time')) {
                $login_time = $this->get('login_time');
            }
            else {
                $login_time = false;
            }
            $res = format_pop3_message_list($this->get('pop3_mailbox_page'), $this, $style, $login_time, $this->get('list_path'));
            $this->out('formatted_message_list', $res);
        }
    }
}

/**
 * Output POP3 server ids
 * @subpackage pop3/output
 */
class Hm_Output_pop3_server_ids extends Hm_Output_Module {
    /**
     * Put a list of POP3 server ids in a hidden page element
     */
    protected function output() {
        return '<input type="hidden" class="pop3_server_ids" value="'.$this->html_safe(implode(',', array_keys($this->get('pop3_servers', array())))).'" />';
    }
}

/**
 * Format a POP3 status response row
 * @subpackage pop3/output
 */
class Hm_Output_display_pop3_status extends Hm_Output_Module {
    /**
     * Build the HTML for a POP3 status row on the home page
     */
    protected function output() {
        $res = '';
        foreach ($this->get('pop3_servers', array()) as $index => $vals) {
            if ($vals['name'] == 'Default-Auth-Server') {
                $vals['name'] = $this->trans('Default');
            }
            $res .= '<tr><td>POP3</td><td>'.$vals['name'].'</td><td class="pop3_status_'.$index.'"></td>'.
                '<td class="pop3_detail_'.$index.'"></td></tr>';
        }
        return $res;
    }
}

/**
 * Format the start of the POP3 section of the settings page
 * @subpackage pop3/output
 */
class Hm_Output_start_pop3_settings extends Hm_Output_Module {
    /**
     * Build the HTML for the heading of the POP3 settings section
     */
    protected function output() {
        return '<tr><td data-target=".pop3_setting" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" />'.$this->trans('POP3 Settings').'</td></tr>';
    }
}

/**
 * Format the message since setting on the settings page
 * @subpackage pop3/output
 */
class Hm_Output_pop3_since_setting extends Hm_Output_Module {
    /**
     * Build the HTML for the message since setting
     */
    protected function output() {
        $since = false;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('pop3_since', $settings)) {
            $since = $settings['pop3_since'];
        }
        return '<tr class="pop3_setting"><td><label for="pop3_since">'.$this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'pop3_since', $this).'</td></tr>';
    }
}

/**
 * Format the message limit setting
 * @subpackage pop3/output
 */
class Hm_Output_pop3_limit_setting extends Hm_Output_Module {
    /**
     * Build the HTML for the message limit setting
     */
    protected function output() {
        $limit = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('pop3_limit', $settings)) {
            $limit = $settings['pop3_limit'];
        }
        return '<tr class="pop3_setting"><td><label for="pop3_limit">'.$this->trans('Max messages to display').'</label></td>'.
            '<td><input type="text" id="pop3_limit" name="pop3_limit" size="2" value="'.$this->html_safe($limit).'" /></td></tr>';
    }
}

/**
 * Format POP3 status response data
 * @subpackage pop3/output
 */
class Hm_Output_filter_pop3_status_data extends Hm_Output_Module {
    /**
     * Build ajax response for a status row on the home page
     */
    protected function output() {
        if ($this->get('pop3_connect_status') == 'Authenticated') {
            $this->out('pop3_status_display', '<span class="online">'.
                $this->trans(ucwords($this->get('pop3_connect_status'))).'</span> in '.round($this->get('pop3_connect_time'), 3));
        }
        else {
            $this->out('pop3_status_display', '<span class="down">'.$this->trans('Down').'</span>');
        }
    }
}

/**
 * Format a list of POP3 messages
 * @subpackage pop3/functions
 * @param array $msg_list list of message data
 * @param object $mod Hm_Output_Module
 * @param string $style list style
 * @param string $login_time timestamp of last login
 * @param string $list_parent list type
 * @return array
 */
function format_pop3_message_list($msg_list, $output_module, $style, $login_time, $list_parent) {
    $res = array();
    foreach($msg_list as $msg_id => $msg) {
        if ($msg['server_name'] == 'Default-Auth-Server') {
            $msg['server_name'] = 'Default';
        }
        $id = sprintf("pop3_%s_%s", $msg['server_id'], $msg_id);
        $subject = display_value('subject', $msg);;
        $from = display_value('from', $msg);
        if ($style == 'email' && !$from) {
            $from = '[No From]';
        }
        $date = display_value('date', $msg);
        if ($date) {
            $date = translate_time_str($date, $output_module);
        }
        $timestamp = display_value('date', $msg, 'time');
        $url = '?page=message&uid='.$msg_id.'&list_path='.sprintf('pop3_%d', $msg['server_id']).'&list_parent='.$list_parent;
        if (Hm_POP3_Uid_Cache::is_read($id)) {
            $flags = array();
        }
        elseif (Hm_POP3_Uid_Cache::is_unread($id)) {
            $flags = array('unseen');
        }
        elseif (isset($msg['date']) && $login_time && strtotime($msg['date']) <= $login_time) {
            $flags = array();
        }
        else {
            $flags = array('unseen');
        }
        if ($style == 'news') {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('icon_callback', $flags),
                    array('subject_callback', $subject, $url, $flags),
                    array('safe_output_callback', 'source', $msg['server_name']),
                    array('safe_output_callback', 'from', $from),
                    array('date_callback', $date, $timestamp),
                ),
                $id,
                $style,
                $output_module
            );
        }
        else {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('safe_output_callback', 'source', $msg['server_name']),
                    array('safe_output_callback', 'from', $from),
                    array('subject_callback', $subject, $url, $flags),
                    array('date_callback', $date, $timestamp),
                    array('icon_callback', $flags)
                ),
                $id,
                $style,
                $output_module
            );
        }
    }
    return $res;
}

/**
 * Search a POP3 message
 * @subpackage pop3/functions
 * @param string $body message body
 * @param array $headers message headers
 * @param string $terms search terms
 * @param string $fld field to search
 * @return bool
 */
function search_pop3_msg($body, $headers, $terms, $fld) {
    if ($fld == 'TEXT') {
        if (stristr($body, $terms)) {
            return true;
        }
    }
    if ($fld == 'SUBJECT') {
        if (array_key_exists('subject', $headers) && stristr($headers['subject'], $terms)) {
            return true;
        }
    }
    if ($fld == 'FROM') {
        if (array_key_exists('from', $headers) && stristr($headers['from'], $terms)) {
            return true;
        }
    }
}

?>
