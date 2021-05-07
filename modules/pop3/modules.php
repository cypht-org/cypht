<?php

/**
 * POP3 modules
 * @package modules
 * @subpackage pop3
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/pop3/hm-pop3.php';

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
                $this->out('list_path', $path, false);
                $details = Hm_POP3_List::dump($matches[1]);
                $title = array('POP3', $details['name'], 'INBOX');
                if ($this->get('list_page', 0)) {
                    $title[] = sprintf('Page %d', $this->get('list_page', 0));
                }
                if (!empty($details)) {
                    $this->out('list_meta', false);
                    $this->out('mailbox_list_title', $title);
					$this->out('custom_list_controls', ' ');
                }
            }
        }
        if (array_key_exists('page', $this->request->get) && $this->request->get['page'] == 'search') {
            $this->out('list_path', 'search', false);
        }
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
                if (pop3_authed($pop3)) {
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
            $server_ids = array();
            foreach ($id_list as $msg_id) {
                if (preg_match("/^pop3_(\d)+_(\d)+$/", $msg_id)) {
                    $parts = explode('_', $msg_id);
                    $server_ids[] = $parts[1];
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
            if (count($server_ids) > 0) {
                foreach ($server_ids as $id) {
                    bust_pop3_cache($this->cache, $id);
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
            $page = 1;
            $terms = false;
            if (array_key_exists('list_page', $this->request->get)) {
                $page = $this->request->get['list_page'];
            }
            if (array_key_exists('pop3_search', $this->request->post)) {
                $limit = DEFAULT_PER_SOURCE;
                $terms = $this->session->get('search_terms', false);
                if (!$terms) {
                    return;
                }
                $since = $this->session->get('search_since', DEFAULT_SINCE);
                $fld = $this->session->get('search_fld', 'TEXT');
                $date = process_since_argument($since);
                $cutoff_timestamp = strtotime($date);
            }
            elseif ($this->get('list_path') == 'unread' || (array_key_exists('pop3_unread_only', $this->request->post) && $this->request->post['pop3_unread_only'])) {
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
                $limit = DEFAULT_PER_SOURCE;
                $date = false;
                $cutoff_timestamp = strtotime($date);
            }
            $cache = false;
            if (!$unread_only) {
                $cache = Hm_POP3_List::get_cache($this->cache, $form['pop3_server_id']);
            }
            if ($cache) {
                $this->out('pop3_cache_used', true);
            }
            $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], $cache);
            $details = Hm_POP3_List::dump($form['pop3_server_id']);
            $path = sprintf("pop3_%d", $form['pop3_server_id']);
            if (pop3_authed($pop3)) {
                $this->out('pop3_mailbox_page_path', $path);
                $list = array_reverse(array_unique(array_keys($pop3->mlist())));
                $total = count($list);
                $list = array_slice($list, (($page - 1) * $limit), $limit);
                foreach ($list as $id) {
                    $msg_headers = $pop3->msg_headers($id);
                    if (!empty($msg_headers)) {
                        if ($date && isset($msg_headers['date'])) {
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
                $this->out('list_page', $page);
                if (!$date) {
                    $this->out('page_links', build_page_links($limit, $page, $total, $path));
                }
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
            $id = (int) substr($form['pop3_list_path'], 5);
            $cache = Hm_POP3_List::get_cache($this->cache, $id);
            $pop3 = Hm_POP3_List::connect($id, $cache);
            $details = Hm_POP3_List::dump($id);
            if (pop3_authed($pop3)) {
                $msg_lines = $pop3->retr_full($form['pop3_uid']);
                $header_list = array();
                $body = array();
                $headers = true;
                $last_header = false;

                $bodies = array();
                $has_multipart = false;
                $boundary = '';
                $boundaries = array();
                $multipart_headers = false;
                $multipart_header_list = array();

                foreach ($msg_lines as $line) {
                    if ($headers) {
                        if (substr($line, 0, 1) == "\t"
                            || substr($line, 0, 1) == " ") {
                            $header_list[$last_header] .= decode_fld($line);
                        }
                        elseif (strstr($line, ':')) {
                            $parts = explode(':', $line, 2);
                            if (count($parts) == 2) {
                                $header_list[$parts[0]] = decode_fld($parts[1]);
                                $last_header = $parts[0];
                            }
                        }
                        if (array_key_exists('Content-Type', $header_list)
                            && strstr($header_list['Content-Type'], 'multipart')
                            && $boundary = strstr($header_list['Content-Type'], 'boundary=')) {
                            $has_multipart = true;
                            $boundary = str_replace('boundary=', '', $boundary);
                            $boundary = str_replace('"', '', $boundary);
                            if (array_search($boundary, $boundaries) === false) {
                                $boundaries[] = $boundary;
                            }
                        }
                    }
                    elseif ($multipart_headers) {
                        if (substr($line, 0, 1) == "\t"
                            || substr($line, 0, 1) == " ") {
                            $multipart_header_list[$last_header] .= decode_fld($line);
                        }
                        elseif (strstr($line, ':')) {
                            $parts = explode(':', $line, 2);
                            if (count($parts) == 2) {
                                $multipart_header_list[$parts[0]] = decode_fld($parts[1]);
                                $last_header = $parts[0];
                            }
                        }
                        if (array_key_exists('Content-Type', $multipart_header_list)
                            && strstr($multipart_header_list['Content-Type'], 'multipart')
                            && $boundary = strstr($multipart_header_list['Content-Type'], 'boundary=')) {
                            $has_multipart = true;
                            $boundary = str_replace('boundary=', '', $boundary);
                            $boundary = str_replace('"', '', $boundary);
                            if (array_search($boundary, $boundaries) === false) {
                                $boundaries[] = $boundary;
                            }
                        }
                    }
                    else {
                        $boundary = '####unmatch-boundary';
                        foreach ($boundaries as $_boundary) {
                            if (strstr($line, $_boundary)) {
                                $boundary = $_boundary;
                                break;
                            }
                        }
                        if ($has_multipart === true && strstr($line, $boundary)) {
                            if (array_key_exists('Content-Type', $multipart_header_list)
                                && strstr($multipart_header_list['Content-Type'], 'html')) {
                                $body['content-type'] = 'html';
                            }
                            else {
                                $body['content-type'] = 'text';
                            }
                            if (array_key_exists('Content-Transfer-Encoding', $multipart_header_list)
                                && strstr($multipart_header_list['Content-Transfer-Encoding'], 'base64')) {
                                $body['text'] = base64_decode(str_replace(array("\r", "\n"), '', $body['text']));
                            }
                            if (array_key_exists('Content-Type', $multipart_header_list)
                                && $charset = strstr($multipart_header_list['Content-Type'], 'charset=')) {
                                $charset = str_replace('charset=', '', $charset);
                                $body['text'] = mb_convert_encoding($body['text'], 'UTF-8', $charset);
                            }
                            if (array_key_exists('text', $body)) {
                                $bodies[] = $body;
                            }
                            $body = array('text' => '');
                            
                            $multipart_headers = true;
                            $multipart_header_list = array();
                        }
                        else {
                            if (array_key_exists('text', $body)) {
                                $body['text'] .= $line;
                            }
                            else {
                                $body['text'] = $line;
                            }
                        }
                    }

                    if (!trim($line)) {
                        $headers = false;
                        if ($multipart_headers === true) {
                            $multipart_headers = false;
                        }
                    }
                }
                $this->out('pop3_message_headers', $header_list);

                if ($has_multipart === false) {
                    if (array_key_exists('Content-Type', $header_list)
                        && strstr($header_list['Content-Type'], 'html')) {
                        $body['content-type'] = 'html';
                    }
                    if (array_key_exists('Content-Transfer-Encoding', $header_list)
                        && strstr($header_list['Content-Transfer-Encoding'], 'base64')) {
                        $body['text'] = base64_decode(str_replace(array("\r", "\n"), '', $body['text']));
                    }
                    if (array_key_exists('Content-Type', $header_list)
                        && $charset = strstr($header_list['Content-Type'], 'charset=')) {
                        $charset = trim(str_replace('charset=', '', $charset));
                        if (strtolower($charset) != 'utf-8') {
                            $body['text'] = mb_convert_encoding($body['text'], 'UTF-8', $charset);
                        }
                    }
                    $bodies[] = $body;
                }

                $this->out('pop3_message_body', $bodies);
                $this->out('pop3_mailbox_page_path', $form['pop3_list_path']);
                $this->out('pop3_server_id', $id);
                bust_pop3_cache($this->cache, $id);
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
                return;
            }
            if (in_server_list('Hm_POP3_List', $form['pop3_server_id'], $form['pop3_user'])) {
                Hm_Msgs::add('ERRThis server and username are already configured');
                return;
            }
            $pop3 = Hm_POP3_List::connect($form['pop3_server_id'], false, $form['pop3_user'], $form['pop3_pass'], true);
            if (pop3_authed($pop3)) {
                $just_saved_credentials = true;
                Hm_Msgs::add("Server saved");
                $this->session->record_unsaved('POP3 server saved');
            }
            else {
                Hm_POP3_List::forget_credentials($form['pop3_server_id']);
                Hm_Msgs::add("ERRUnable to save this server, are the username and password correct?");
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
 * Save a POP3 server cache
 * @subpackage pop3/handler
 */
class Hm_Handler_save_pop3_cache extends Hm_Handler_Module {
    /**
     * Save a POP3 server cache in the session
     *
     */
    public function process() {
        $cache = array();
        if ($this->get('pop3_cache_used')) {
            return;
        }
        $servers = Hm_POP3_List::dump(false, true);
        foreach ($servers as $id => $server) {
            if (isset($server['object']) && is_object($server['object'])) {
                $cache[$id] = $server['object']->dump_cache();
            }
        }
        if (count($cache) > 0) {
            foreach ($cache as $id => $data) {
                $this->cache->set('pop3'.$id, $cache[$id]);
            }
        }
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
                else {
                    $callback = 'pop3_unread_background';
                }
                break;
        }
        if ($callback) {
            foreach (Hm_POP3_List::dump() as $index => $vals) {
                if ($server_id !== false && $server_id != $index) {
                    continue;
                }
                if ($callback == 'pop3_unread_background') {
                    $this->append('data_sources', array('callback' => $callback, 'group' => 'background', 'type' => 'pop3', 'name' => $vals['name'], 'id' => $index));
                }
                else {
                    $this->append('data_sources', array('callback' => $callback, 'type' => 'pop3', 'name' => $vals['name'], 'id' => $index));
                }
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
        $max = 0;
        foreach ($servers as $index => $server) {
            Hm_POP3_List::add( $server, $index );
            if (array_key_exists('default', $server) && $server['default']) {
                $added = true;
            }
            $max = $index;
        }
        $max++;
        if (!$added) {
            $auth_server = $this->session->get('pop3_auth_server_settings', array());
            if (array_key_exists('name', $auth_server)) {
                $name = $auth_server['name'];
            }
            else {
                $name = $this->config->get('pop3_auth_name', 'Default');
            }
            if (!empty($auth_server)) {
                Hm_POP3_List::add(array( 
                    'name' => $name,
                    'default' => true,
                    'server' => $auth_server['server'],
                    'port' => $auth_server['port'],
                    'tls' => $auth_server['tls'],
                    'user' => $auth_server['username'],
                    'pass' => $auth_server['password']),
                $max);
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
                if (array_key_exists('tls', $this->request->post) && $this->request->post['tls']) {
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
        foreach ($servers as $index => $vals) {
            if (array_key_exists('object', $vals) && $vals['object']) {
                unset($servers[$index]['object']);
            }
        }
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
        if ($this->get('single_server_mode')) {
            return '';
        }
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
            '<input required type="number" id="new_pop3_port" name="new_pop3_port" class="port_fld" value="995" placeholder="'.$this->trans('Port').'"></td></tr>'.
            '<tr><td><input type="radio" name="tls" value="1" id="pop3_tls" checked="checked" /> <label for="pop3_tls">'.$this->trans('Use TLS').'</label>'.
            '<br /><input type="radio" name="tls" value="0" id="pop3_notls"><abel for="pop3_notls">'.$this->trans('STARTTLS or unencrypted').'</label></td>'.
            '</tr><tr><td><input type="submit" value="'.$this->trans('Add').'" name="submit_pop3_server" /></td></tr>'.
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
        if ($this->get('single_server_mode')) {
            return '';
        }
        $res = '';
        foreach ($this->get('pop3_servers', array()) as $index => $vals) {

            $no_edit = false;

            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
            }
            elseif (array_key_exists('user', $vals) && array_key_exists('nopass', $vals)) {
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('Password');
                $disabled = '';
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
                '<input '.$disabled.' id="pop3_user_'.$index.'" class="credentials" placeholder="'.$this->trans('Username').'" type="text" name="pop3_user" value="'.$this->html_safe($user_pc).'"></span>'.
                '<span> <label class="screen_reader" for="pop3_password_'.$index.'">'.$this->trans('POP3 password').'</label>'.
                '<input '.$disabled.' id="pop3_password_'.$index.'" class="credentials pop3_password" placeholder="'.$pass_pc.'" type="password" name="pop3_pass"></span>';
            if (!$no_edit) {
                if (!isset($vals['user']) || !$vals['user']) {
                    $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="delete_pop3_connection" />';
                    $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_pop3_connection" />';
                }
                else {
                    $res .= '<input type="submit" value="Test" class="test_pop3_connect" />';
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
                '<a data-id="pop3_'.$this->html_safe($id).'" href="?page=message_list&list_path=pop3_'.$this->html_safe($id).'">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" alt="Toggle folder" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> ';
            }
            $res .= $this->html_safe($folder).'</a></li>';
        }
        if ($res) {
            $this->append('folder_sources', array('email_folders', $res));
        }
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
                '<a href="#" class="header_toggle">'.$this->trans('All').'</a>'.
                '<a class="header_toggle" style="display: none;" href="#">small</a>'.
                ' | <a href="?page=compose">'.$this->trans('Reply').'</a>'.
                ' | <a href="?page=compose">'.$this->trans('Forward').'</a>'.
                ' | <a href="?page=compose">'.$this->trans('Attach').'</a>'.
                ' | <a data-message-part="0" href="#">'.$this->trans('raw').'</a>'.
                ' | <a href="#">'.$this->trans('Flag').'</a>'.
                '</th></tr></table>';

            $this->out('msg_headers', $txt);
        }
        $txt = '<div class="msg_text_inner">';
        if ($this->get('pop3_message_body')) {
            foreach ($this->get('pop3_message_body') as $body) {
                if (array_key_exists('content-type', $body) && $body['content-type'] === 'html') {
                    $txt .= format_msg_html($body['text']);
                }
                else {
                    $txt .= format_msg_text($body['text'], $this);
                }
            }
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
        $res = array();
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
        }
        $this->out('formatted_message_list', $res);
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
if (!hm_exists('format_pop3_message_list')) {
function format_pop3_message_list($msg_list, $output_module, $style, $login_time, $list_parent) {
    $res = array();
    $show_icons = $output_module->get('msg_list_icons');
    foreach($msg_list as $msg_id => $msg) {
        $icon = 'env_open';
        $row_class = 'email';
        if ($msg['server_name'] == 'Default-Auth-Server') {
            $msg['server_name'] = 'Default';
        }
        $id = sprintf("pop3_%s_%s", $msg['server_id'], $msg_id);
        $subject = display_value('subject', $msg, false, $output_module->trans('[No Subject]'));
        $from = display_value('from', $msg);
        $nofrom = '';
        if ($style == 'email' && !$from) {
            $nofrom = ' nofrom';
            $from = '[No From]';
        }
        $date = display_value('date', $msg, false, $output_module->trans('[No Date]'));
        if ($date) {
            $date = translate_time_str($date, $output_module);
        }
        $timestamp = display_value('date', $msg, 'time');
        $url = '?page=message&uid='.$msg_id.'&list_path='.sprintf('pop3_%d', $msg['server_id']).'&list_parent='.$list_parent;
        if ($output_module->get('list_page', 0)) {
            $url .= '&list_page='.$output_module->html_safe($output_module->get('list_page', 1));
        }
        if (Hm_POP3_Uid_Cache::is_read($id)) {
            $flags = array();
        }
        elseif (Hm_POP3_Uid_Cache::is_unread($id)) {
            $flags = array('unseen');
            $icon = 'env_closed';
            $row_class .= ' unseen';
        }
        elseif (isset($msg['date']) && $login_time && strtotime($msg['date']) <= $login_time) {
            $flags = array();
        }
        else {
            $icon = 'env_closed';
            $flags = array('unseen');
        }
        $row_class .= ' '.str_replace(' ', '_', $msg['server_name']);
        if (!$show_icons) {
            $icon = false;
        }
        if ($style == 'news') {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('icon_callback', $flags),
                    array('subject_callback', $subject, $url, $flags, $icon),
                    array('safe_output_callback', 'source', $msg['server_name']),
                    array('safe_output_callback', 'from'.$nofrom, $from),
                    array('date_callback', $date, $timestamp),
                ),
                $id,
                $style,
                $output_module,
                $row_class
            );
        }
        else {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('safe_output_callback', 'source', $msg['server_name'], $icon),
                    array('safe_output_callback', 'from'.$nofrom, $from),
                    array('subject_callback', $subject, $url, $flags),
                    array('date_callback', $date, $timestamp),
                    array('icon_callback', $flags)
                ),
                $id,
                $style,
                $output_module,
                $row_class
            );
        }
    }
    return $res;
}}

/**
 * Search a POP3 message
 * @subpackage pop3/functions
 * @param string $body message body
 * @param array $headers message headers
 * @param string $terms search terms
 * @param string $fld field to search
 * @return bool
 */
if (!hm_exists('search_pop3_msg')) {
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
}}

/**
 * @subpackage pop3/functions
 */
if (!hm_exists('bust_pop3_cache')) {
function bust_pop3_cache($hm_cache, $id) {
    $hm_cache->del('pop3'.$id);
    Hm_Debug::add('Busted POP3 cache for id '.$id);
}}

/**
 * @subpackage pop3/functions
 */
if (!hm_exists('pop3_authed')) {
function pop3_authed($pop3) {
    return is_object($pop3) && $pop3->state == 'authed';
}}

