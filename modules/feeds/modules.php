<?php

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/feeds/hm-feed.php';

class Hm_Handler_feed_list_type extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if ($path == 'feeds') {
                $this->out('list_path', 'feeds');
                $this->out('mailbox_list_title', array('All Feeds'));
                $this->out('message_list_since', $this->user_config->get('feed_since', DEFAULT_SINCE));
                $this->out('per_source_limit', $this->user_config->get('feed_limit', DEFAULT_SINCE));
            }
            elseif (preg_match("/^feeds_\d+$/", $path)) {
                $this->out('message_list_since', $this->user_config->get('feed_since', DEFAULT_SINCE));
                $this->out('per_source_limit', $this->user_config->get('feed_limit', DEFAULT_SINCE));
                $this->out('list_path', $path);
                $parts = explode('_', $path, 2);
                $details = Hm_Feed_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    $this->out('mailbox_list_title', array('Feeds', $details['name']));
                }
            }
        }
    }
}

class Hm_Handler_process_feed_limit_setting extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'feed_limit'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if ($form['feed_limit'] > MAX_PER_SOURCE || $form['feed_limit'] < 0) {
                $limit = DEFAULT_PER_SOURCE;
            }
            else {
                $limit = $form['feed_limit'];
            }
            $new_settings['feed_limit'] = $limit;
        }
        else {
            $settings['feed_limit'] = $this->user_config->get('feed_limit', DEFAULT_PER_SOURCE);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

class Hm_Handler_process_feed_since_setting extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings', 'feed_since'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            $new_settings['feed_since'] = process_since_argument($form['feed_since'], true);
        }
        else {
            $settings['feed_since'] = $this->user_config->get('feed_since', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

class Hm_Handler_process_unread_feeds_setting extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('save_settings'));
        $new_settings = $this->get('new_user_settings', array());
        $settings = $this->get('user_settings', array());

        if ($success) {
            if (array_key_exists('unread_exclude_feeds', $this->request->post)) {
                $new_settings['unread_exclude_feeds'] = true;
            }
            else {
                $new_settings['unread_exclude_feeds'] = false;
            }
        }
        else {
            $settings['unread_exclude_feeds'] = $this->user_config->get('unread_exclude_feeds', false);
        }
        $this->out('new_user_settings', $new_settings, false);
        $this->out('user_settings', $settings, false);
    }
}

class Hm_Handler_feed_connect extends Hm_Handler_Module {
    public function process() {
        $failed = true; 
        if (isset($this->request->post['feed_connect'])) {
            list($success, $form) = $this->process_form(array('feed_id'));
            if ($success) {
                $feed_data = Hm_Feed_List::dump($form['feed_id']);
                if ($feed_data) {
                    $feed = is_feed($feed_data['server']);
                    if ($feed) {
                        $failed = false;
                        Hm_Msgs::add("Successfully connected to the feed");
                    }
                }
            }
            if ($failed) {
                Hm_Msgs::add("ERRFailed to connect to the feed");
            }
        }
    }
}

class Hm_Handler_delete_feed extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['delete_feed'])) {
            list($success, $form) = $this->process_form(array('feed_id'));
            if ($success) {
                $res = Hm_Feed_List::del($form['feed_id']);
                if ($res) {
                    $this->out('deleted_server_id', $form['feed_id']);
                    $this->out('reload_folders', true);
                    Hm_Msgs::add('Feed deleted');
                    $this->session->record_unsaved('Feed deleted');
                }
            }
            else {
                $this->out('old_form', $form);
            }
        }
    }
}

class Hm_Handler_feed_status extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('feed_server_ids'));
        if ($success) {
            $ids = explode(',', $form['feed_server_ids']);
            foreach ($ids as $id) {
                $start_time = microtime(true);
                $feed_data = Hm_Feed_List::dump($id);
                if ($feed_data) {
                    $feed = is_feed($feed_data['server']);
                    if ($feed && $feed->parsed_data) {
                        $this->out('feed_connect_time', microtime(true) - $start_time);
                        $this->out('feed_connect_status', 'Connected');
                        $this->out('feed_status_server_id', $id);
                    }
                }
            }
        }
    }
}

class Hm_Handler_feed_message_action extends Hm_Handler_Module {
    public function process() {

        list($success, $form) = $this->process_form(array('action_type', 'message_ids'));
        if ($success) {
            $id_list = explode(',', $form['message_ids']);
            foreach ($id_list as $msg_id) {
                if (preg_match("/^feeds_(\d)+_.+$/", $msg_id)) {
                    $parts = explode('_', $msg_id, 3);
                    $guid = $parts[2];
                    switch($form['action_type']) {
                        case 'unread':
                            Hm_Feed_Seen_Cache::remove($guid);
                            break;
                        case 'read':
                            Hm_Feed_Seen_Cache::add($guid);
                            break;
                    }
                }
            }
        }
    }
}

class Hm_Handler_feed_list_content extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('feed_server_ids'));
        if ($success) {
            if (array_key_exists('feed_search', $this->request->post)) {
                $terms = $this->session->get('search_terms', false);
                $since = $this->session->get('search_since', DEFAULT_SINCE);
                $fld = $this->session->get('search_fld', 'TEXT');
            }
            else {
                $terms = false;
            }
            $ids = explode(',', $form['feed_server_ids']);
            $res = array();
            $unread_only = false;
            $login_time = $this->session->get('login_time', false);
            if ($login_time) {
                $this->out('login_time', $login_time);
            }
            if ($this->get('list_path') == 'unread') {
                $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE));
                $unread_only = true;
                $cutoff_timestamp = strtotime($date);
                if ($login_time && $login_time > $cutoff_timestamp) {
                    $cutoff_timestamp = $login_time;
                }
            }
            elseif ($this->get('list_path') == 'combined_inbox') {
                $limit = $this->user_config->get('all_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('all_since_setting', DEFAULT_SINCE));
                $cutoff_timestamp = strtotime($date);
            }
            else {
                $limit = $this->user_config->get('feed_limit', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('feed_since', DEFAULT_SINCE)); 
                $cutoff_timestamp = strtotime($date);
            }
            foreach($ids as $id) {
                $feed_data = Hm_Feed_List::dump($id);
                if ($feed_data) {
                    $feed = is_feed($feed_data['server'], $limit);
                    if ($feed && $feed->parsed_data) {
                        foreach ($feed->parsed_data as $item) {
                            if (isset($item['pubdate']) && strtotime($item['pubdate']) < $cutoff_timestamp) {
                                continue;
                            }
                            elseif (isset($item['dc:date']) && strtotime($item['dc:date']) < $cutoff_timestamp) {
                                continue;
                            }
                            if (isset($item['guid']) && $unread_only && Hm_Feed_Seen_Cache::is_present(md5($item['guid']))) {
                                continue;
                            }
                            if ($terms && !search_feed_item($item, $terms, $since, $fld)) {
                                continue;
                            }
                            else {
                                $item['server_id'] = $id;
                                $item['server_name'] = $feed_data['name'];
                                $res[] = $item;
                            }
                        }
                    }
                }
            }
            $this->out('feed_list_data', $res);
            if (isset($this->request->get['list_path'])) {
                $this->out('feed_list_parent', $this->request->get['list_path']);
            }
            $this->out('feed_server_ids', $form['feed_server_ids']);
        }
    }
}

class Hm_Handler_feed_item_content extends Hm_Handler_Module {
    public function process() {
        $content = '';
        $headers = array();
        list($success, $form) = $this->process_form(array('feed_uid', 'feed_list_path'));
        if ($success) {
            $path = explode('_', $form['feed_list_path']);
            $id = $path[1];
            $feed_data = Hm_Feed_List::dump($id);
            if ($feed_data) {
                $feed = is_feed($feed_data['server']);
                if ($feed && $feed->parsed_data) {
                    foreach ($feed->parsed_data as $item) {
                        if (isset($item['id']) && !isset($item['guid'])) {
                            $item['guid'] = $item['id'];
                            unset($item['id']);
                        }
                        elseif (isset($item['title']) && !isset($item['guid'])) {
                            $item['guid'] = md5($item['title']);
                        }
                        if (isset($item['guid']) && md5($item['guid']) == $form['feed_uid']) {
                            if (isset($item['description'])) {
                                $content = $item['description'];
                                unset($item['description']);
                            }
                            elseif (isset($item['summary'])) {
                                $content = $item['summary'];
                                unset($item['summary']);
                            }
                            $title = $item['title'];
                            $headers = $item;
                            unset($headers['title']);
                            $headers = array_merge(array('title' => $title), $headers);
                            $headers['source'] = $feed_data['name'];
                            break;
                        }
                    }
                }
            }
            if ($content) {
                Hm_Feed_Seen_Cache::add($form['feed_uid']);
                $this->out('feed_message_content', $content);
                $this->out('feed_message_headers', $headers);
            }
        }
    }
}

class Hm_Handler_process_add_feed extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['submit_feed'])) {
            $found = false;
            list($success, $form) = $this->process_form(array('new_feed_name', 'new_feed_address'));
            if ($success) {
                $connection_test = address_from_url($form['new_feed_address']);
                if ($con = @fsockopen($connection_test, 80, $errno, $errstr, 2)) {
                    $feed = is_feed($form['new_feed_address']);
                    if (!$feed) {
                        $feed = new Hm_Feed();
                        $homepage = $feed->get_feed_data($form['new_feed_address']);
                        if (trim($homepage)) {
                            list($type, $href) = search_for_feeds($homepage);
                            if ($type && $href) {
                                Hm_Msgs::add('Discovered a feed at that address');
                                $found = true;
                            }
                            else {
                                Hm_Msgs::add('ERRCould not find an RSS or ATOM feed at that address');
                            }
                        }
                        else {
                            Hm_Msgs::add('ERRCound not find a feed at that address');
                        }
                    }
                    else {
                        Hm_Msgs::add('Successfully connected to feed');
                        $found = true;
                        if (stristr('<feed', $feed->xml_data)) {
                            $type = 'application/atom+xml';
                        }
                        else {
                            $type = 'application/rss+xml';
                        }
                        $href = $form['new_feed_address'];
                    }
                }
                else {
                    Hm_Msgs::add(sprintf('ERRCound not add feed: %s', $errstr));
                }
            }
            else {
                Hm_Msgs::add('ERRFeed Name and Address are required');
            }
            if ($found) {
                $this->out('reload_folders', true);
                Hm_Feed_List::add(array(
                    'name' => $form['new_feed_name'],
                    'server' => $href,
                    'tls' => false,
                    'port' => 80
                ));
                $this->session->record_unsaved('Feed added');
            }
        }
    }
}

class Hm_Handler_load_feeds_from_config extends Hm_Handler_Module {
    public function process() {
        $feeds = $this->user_config->get('feeds', array());
        foreach ($feeds as $index => $feed) {
            Hm_Feed_List::add($feed, $index);
        }
        Hm_Feed_Seen_Cache::load($this->session->get('feed_read_uids', array()));
    }
}

class Hm_Handler_add_feeds_to_page_data extends Hm_Handler_Module {
    public function process() {
        $excluded = false;
        if ($this->get('list_path') == 'unread') {
            $excluded = $this->user_config->get('unread_exclude_feeds', false);
        }
        if ($excluded) {
            return; 
        }
        $feeds = Hm_Feed_List::dump();
        if (!empty($feeds)) {
            $this->out('feeds', $feeds);
            $this->append('folder_sources', 'feeds_folders');
        }
    }
}

class Hm_Handler_save_feeds extends Hm_Handler_Module {
    public function process() {
        $feeds = Hm_Feed_List::dump();
        $this->user_config->set('feeds', $feeds);
        $this->session->set('feed_read_uids', Hm_Feed_Seen_Cache::dump());
    }
}

class Hm_Handler_load_feed_folders extends Hm_Handler_Module {
    public function process() {
        $feeds = Hm_Feed_List::dump();
        $folders = array();
        if (!empty($feeds)) {
            foreach ($feeds as $id => $feed) {
                $folders[$id] = $feed['name'];
            }
        }
        $this->out('feed_folders', $folders);
    }
}

class Hm_Output_add_feed_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            $count = count($this->get('feeds', array()));
            $count = sprintf($this->trans('%d configured'), $count);
            return '<div class="feed_server_setup"><div data-target=".feed_section" class="server_section">'.
                '<img alt="" src="'.Hm_Image_Sources::$rss.'" width="16" height="16" />'.
                ' Feeds <div class="server_count">'.$count.'</div></div><div class="feed_section"><form class="add_server" method="POST">'.
                '<input type="hidden" name="hm_nonce" value="'.$this->html_safe(Hm_Nonce::generate()).'" />'.
                '<div class="subtitle">Add an RSS/ATOM Feed</div><table>'.
                '<tr><td><input required type="text" name="new_feed_name" class="txt_fld" value="" placeholder="Feed name" /></td></tr>'.
                '<tr><td><input required type="text" name="new_feed_address" class="txt_fld" placeholder="Site address or feed URL" value=""/></td></tr>'.
                '<tr><td><input type="submit" value="Add" name="submit_feed" /></td></tr>'.
                '</table></form>';
        }
    }
}

class Hm_Output_display_configured_feeds extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            foreach ($this->get('feeds', array()) as $index => $vals) {
                $res .= '<div class="configured_server">';
                $res .= sprintf('<div class="server_title">%s</div><div title="%s" class="server_subtitle">%s</div>', $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['server']));
                $res .= '<form class="feed_connect" method="POST">';
                $res .= '<input type="hidden" name="feed_id" value="'.$this->html_safe($index).'" />';
                $res .= '<input type="submit" value="Test" class="test_feed_connect" />';
                $res .= '<input type="submit" value="Delete" class="feed_delete" />';
                $res .= '<input type="hidden" value="ajax_feed_debug" name="hm_ajax_hook" />';
                $res .= '</form></div>';
            }
            $res .= '<br class="clear_float" /></div></div>';
        }
        return $res;
    }
}

class Hm_Output_feed_ids extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<input type="hidden" class="feed_server_ids" value="'.$this->html_safe(implode(',', array_keys($this->get('feeds', array())))).'" />';
    }
}

class Hm_Output_filter_feed_item_content extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($this->get('feed_message_content')) {
            $header_str = '<table class="msg_headers">'.
                '<col class="header_name_col"><col class="header_val_col"></colgroup>';
            foreach ($this->get('feed_message_headers', array()) as $name => $value) {
                if ($name != 'link' && !strstr($value, ' ') && strlen($value) > 75) {
                    $value = substr($value, 0, 75).'...';
                }
                if ($name == 'title') {
                    $header_str .= '<tr class="header_subject"><th colspan="2">'.$this->html_safe($value).'</td></tr>';
                }
                elseif ($name == 'link') {
                    $header_str .= '<tr class="header_'.$name.'"><th>'.$this->html_safe($name).'</th><td><a target="_blank" href="'.$this->html_safe($value).'">'.$this->html_safe($value).'</a></td></tr>';
                }
                else {
                    $header_str .= '<tr><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                }
            }
            $header_str .= '<tr><td colspan="2"></td></tr></table>';
            $this->out('feed_message_content', str_replace(array('<', '>', '&ldquo;'), array(' <', '> ', ' &ldquo;'), $input['feed_message_content']));
            $txt = '<div class="msg_text_inner">'.format_msg_html($this->get('feed_message_content')).'</div>';
            $this->out('feed_msg_text', $txt);
            $this->out('feed_msg_headers', $header_str);
        }
    }
}

class Hm_Output_filter_feed_list_data extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = array();
        $login_time = false;
        if ($this->get('login_time')) {
            $login_time = $this->get('login_time');
        }
        foreach ($this->get('feed_list_data', array()) as $item) {
            if (isset($item['id']) && !isset($item['guid'])) {
                $item['guid'] = $item['id'];
                unset($item['id']);
            }
            elseif (isset($item['title']) && !isset($item['guid'])) {
                $item['guid'] = md5($item['title']);
            }
            if (isset($item['guid'])) {

                $id = sprintf("feeds_%s_%s", $item['server_id'], md5($item['guid']));
                if (isset($item['dc:date'])) {
                    $date = display_value('dc:date', $item, 'date');
                    $timestamp = display_value('dc:date', $item, 'time');
                }
                elseif (isset($item['pubdate'])) {
                    $date = display_value('pubdate', $item, 'date');
                    $timestamp = display_value('pubdate', $item, 'time');
                }
                else {
                    $date = '';
                    $timestamp = 0;
                }
                $url = '?page=message&uid='.urlencode(md5($item['guid'])).'&list_path=feeds_'.$item['server_id'];
                if ($this->get('feed_list_parent') == 'combined_inbox') {
                    $url .= '&list_parent=combined_inbox';
                }
                elseif ($this->get('feed_list_parent') == 'unread') {
                    $url .= '&list_parent=unread';
                }
                elseif ($this->get('feed_list_parent') == 'feeds') {
                    $url .= '&list_parent=feeds';
                }
                else {
                    $url .= '&list_parent=feeds_'.$item['server_id'];
                }
                if ($this->get('news_list_style')) {
                    $style = 'news';
                }
                else {
                    $style = 'email';
                }
                if ($this->get('is_mobile')) {
                    $style = 'news';
                }
                if (Hm_Feed_Seen_Cache::is_present(md5($item['guid']))) {
                    $flags = array();
                }
                elseif ($timestamp && $login_time && $timestamp <= $login_time) {
                    $flags = array();
                }
                else {
                    $flags = array('unseen');
                }
                if (isset($item['author'])) {
                    $from = display_value('author', $item, 'from');
                }
                elseif (isset($item['name'])) {
                    $from = display_value('name', $item, 'from');
                }
                elseif (isset($item['dc:creator'])) {
                    $from = display_value('dc:creator', $item, 'from');
                }
                elseif ($style == 'email') {
                    $from = '[No From]';
                }
                else {
                    $from = '';
                }
                $res[$id] = message_list_row(strip_tags($item['title']), $date, $timestamp, $from, $item['server_name'], $id, $flags, $style, $url, $this);
            }
        }
        $this->out('formatted_message_list', $res);
    }
}

class Hm_Output_filter_feed_folders extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (is_array($this->get('feed_folders'))) {
            $res .= '<li class="menu_feeds"><a class="unread_link" href="?page=message_list&amp;list_path=feeds">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$rss_alt).'" alt="" width="16" height="16" /> '.$this->trans('All').'</a> <span class="unread_feed_count"></span></li>';
            foreach ($this->get('feed_folders') as $id => $folder) {
                $res .= '<li class="feeds_'.$this->html_safe($id).'">'.
                    '<a href="?page=message_list&list_path=feeds_'.$this->html_safe($id).'">'.
                    '<img class="account_icon" alt="Toggle folder" src="'.Hm_Image_Sources::$rss.'" width="16" height="16" /> '.
                    $this->html_safe($folder).'</a></li>';
            }
        }
        Hm_Page_Cache::add('feeds_folders', $res, true);
        return '';
    }
}

class Hm_Output_display_feeds_status extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        foreach ($this->get('feeds', array()) as $index => $vals) {
            $res .= '<tr><td>FEED</td><td>'.$vals['name'].'</td><td class="feeds_status_'.$index.'"></td>'.
                '<td class="feeds_detail_'.$index.'"></td></tr>';
        }
        return $res;
    }
}

class Hm_Output_unread_feeds_included extends Hm_Output_Module {
    protected function output($input, $format) {
        $settings = $this->get('user_settings');
        if (array_key_exists('unread_exclude_feeds', $settings) && $settings['unread_exclude_feeds']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        return '<tr class="unread_setting"><td>Exclude unread feed items</td><td><input type="checkbox" '.$checked.' value="1" name="unread_exclude_feeds" /></td></tr>';
    }
}

class Hm_Output_filter_feed_status_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($this->get('feed_connect_status') == 'Connected') {
            $this->out('feed_status_display', '<span class="online">'.
                $this->html_safe(ucwords($this->get('feed_connect_status'))).'</span> in '.round($this->get('feed_connect_time'), 3));
        }
        else {
            $this->out('feed_status_display', '<span class="down">Down</span>');
        }
    }
}

class Hm_Output_start_feed_settings extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td colspan="2" data-target=".feeds_setting" class="settings_subtitle"><img alt="" src="'.Hm_Image_Sources::$rss.'" />Feed Settings</td></tr>';
    }
}

class Hm_Output_feed_since_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $since = false;
        $settings = $this->get('user_settings');
        if (array_key_exists('feed_since', $settings)) {
            $since = $settings['feed_since'];
        }
        return '<tr class="feeds_setting"><td>Show feed items received since</td><td>'.message_since_dropdown($since, 'feed_since', $this).'</td></tr>';
    }
}

class Hm_Output_feed_limit_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $limit = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings');
        if (array_key_exists('feed_limit', $settings)) {
            $limit = $settings['feed_limit'];
        }
        return '<tr class="feeds_setting"><td>Max feed items to display</td><td><input type="text" name="feed_limit" size="2" value="'.$this->html_safe($limit).'" /></td></tr>';
    }
}

function address_from_url($str) {
    $res = $str;
    $url_bits = parse_url($str);
    if (isset($url_bits['scheme']) && isset($url_bits['host'])) {
        $res = $url_bits['host'];
    }
    return $res;
}

function is_feed($url, $limit=20) {
    $feed = new Hm_Feed();
    $feed->limit = $limit;
    $feed->parse_feed($url);
    $feed_data = array_filter($feed->parsed_data);
    if (empty($feed_data)) {
        return false;
    }
    else {
        return $feed;
    }
}

function search_for_feeds($html) {
    $type = false;
    $href = false;
    if (preg_match_all("/<link.+>/U", $html, $matches)) {
        foreach ($matches[0] as $link_tag) {
            if (stristr($link_tag, 'alternate')) {
                if (preg_match("/type=(\"|'|)(.+)(\"|'|\>| )/U", $link_tag, $types)) {
                    $type = trim($types[2]);
                }
                if (preg_match("/href=(\"|'|)(.+)(\"|'|\>| )/U", $link_tag, $hrefs)) {
                    $href = trim($hrefs[2]);
                }
            }
        }
    }
    return array($type, $href);
}

function search_feed_item($item, $terms, $since, $fld) {

    if (array_key_exists('pubdate', $item)) {
        if (strtotime($item['pubdate']) < strtotime($since)) {
            return false;
        }
    }
    if (array_key_exists('dc:date', $item)) {
        if (strtotime($item['dc:date']) < strtotime($since)) {
            return false;
        }
    }
    switch ($fld) {
        case 'BODY':
            $flds = array('description');
            break;
        case 'FROM':
            $flds = array('dc:creator');
            break;
        case 'SUBJECT':
            $flds = array('title');
            break;
        case 'TEXT':
        default:
            $flds = array('description', 'title', 'dc:creator', 'guid');
            break;
    }
    foreach ($flds as $fld) {
        if (array_key_exists($fld, $item)) {
            if (stristr($item[$fld], $terms)) {
                return true;
            }
        }
    }
    return false;
}

?>
