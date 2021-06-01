<?php

/**
 * Feeds modules
 * @package modules
 * @subpackage feeds
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'modules/feeds/hm-feed.php';

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_feed_list_type extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
            if ($path == 'feeds') {
                $this->out('list_path', 'feeds', false);
                $this->out('mailbox_list_title', array('All Feeds'));
                $this->out('message_list_since', $this->user_config->get('feed_since_setting', DEFAULT_SINCE));
                $this->out('per_source_limit', $this->user_config->get('feed_limit_setting', DEFAULT_PER_SOURCE));
            }
            elseif (preg_match("/^feeds_\d+$/", $path)) {
                $this->out('message_list_since', $this->user_config->get('feed_since_setting', DEFAULT_SINCE));
                $this->out('per_source_limit', $this->user_config->get('feed_limit_setting', DEFAULT_PER_SOURCE));
                $this->out('list_path', $path, false);
                $this->out('custom_list_controls', ' ');
                $parts = explode('_', $path, 2);
                $details = Hm_Feed_List::dump(intval($parts[1]));
                if (!empty($details)) {
                    $this->out('mailbox_list_title', array('Feeds', $details['name']));
                }
            }
        }
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_process_feed_limit_setting extends Hm_Handler_Module {
    public function process() {
        process_site_setting('feed_limit', $this, 'max_source_setting_callback', DEFAULT_PER_SOURCE);
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_process_feed_since_setting extends Hm_Handler_Module {
    public function process() {
        process_site_setting('feed_since', $this, 'since_setting_callback');
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_process_unread_feeds_setting extends Hm_Handler_Module {
    public function process() {
        function unread_feed_setting_callback($val) { return $val; }
        process_site_setting('unread_exclude_feeds', $this, 'unread_feed_setting_callback', false, true);
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_feed_connect extends Hm_Handler_Module {
    public function process() {
        $failed = true; 
        if (isset($this->request->post['feed_connect'])) {
            list($success, $form) = $this->process_form(array('feed_id'));
            if ($success) {
                $feed_data = Hm_Feed_List::dump($form['feed_id']);
                if ($feed_data) {
                    $feed = is_news_feed($feed_data['server']);
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

/**
 * @subpackage feeds/handler
 */
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

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_feed_status extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('feed_server_ids'));
        if ($success) {
            $ids = explode(',', $form['feed_server_ids']);
            foreach ($ids as $id) {
                $start_time = microtime(true);
                $feed_data = Hm_Feed_List::dump($id);
                if ($feed_data) {
                    $feed = is_news_feed($feed_data['server']);
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

/**
 * @subpackage feeds/handler
 */
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
                            Hm_Feed_Uid_Cache::unread($guid);
                            break;
                        case 'read':
                            Hm_Feed_Uid_Cache::read($guid);
                            break;
                    }
                }
            }
        }
    }
}

/**
 * @subpackage feeds/handler
 * @todo this is pretty ugly, try to break up
 */
class Hm_Handler_feed_list_content extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('feed_server_ids'));
        $search = false;
        if ($success) {
            $cache = false;
            if (array_key_exists('feed_search', $this->request->post)) {
                $terms = $this->session->get('search_terms', false);
                $since = $this->session->get('search_since', DEFAULT_SINCE);
                $fld = $this->session->get('search_fld', 'TEXT');
                $search = true;
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
                $limit = $this->user_config->get('feed_limit_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('feed_since_setting', DEFAULT_SINCE)); 
                $cutoff_timestamp = strtotime($date);
            }
            if (!$search || ($search && $terms)) {
                foreach($ids as $id) {
                    $feed_data = Hm_Feed_List::dump($id);
                    if ($feed_data) {
                        $cache = feed_memcached_fetch($this, $feed_data);
                        $data = false;
                        if (is_array($cache) && count($cache) > 0) {
                            $data = $cache;
                        }
                        else {
                            $feed = is_news_feed($feed_data['server'], $limit);
                            if ($feed && $feed->parsed_data) {
                                $data = $feed->parsed_data;
                                $cache = false;
                            }
                        }
                        if (is_array($data)) {
                            foreach ($data as $item) {
                                if (array_key_exists('id', $item) && !array_key_exists('guid', $item)) {
                                    $item['guid'] = $item['id'];
                                }
                                elseif (array_key_exists('link', $item) && !array_key_exists('guid', $item)) {
                                    $item['guid'] = $item['link'];
                                }
                                if (array_key_exists('link_self', $item) || !array_key_exists('guid', $item)) {
                                    continue;
                                }
                                if (!Hm_Feed_Uid_Cache::is_unread(md5($item['guid']))) {
                                    if (isset($item['pubdate']) && strtotime($item['pubdate']) < $cutoff_timestamp) {
                                        continue;
                                    }
                                    elseif (isset($item['dc:date']) && strtotime($item['dc:date']) < $cutoff_timestamp) {
                                        continue;
                                    }
                                    if (isset($item['guid']) && $unread_only && Hm_Feed_Uid_Cache::is_read(md5($item['guid']))) {
                                        continue;
                                    }
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
            }
            if (!$cache) {
                # TODO: fix potential warning about feed_data
                feed_memcached_save($this, $feed_data, $res);
            }
            $this->out('feed_list_data', $res);
            if (isset($this->request->get['list_path'])) {
                $this->out('feed_list_parent', $this->request->get['list_path']);
            }
            elseif (isset($this->request->get['page']) && $this->request->get['page'] == 'search') {
                $this->out('feed_list_parent', 'search');
            }
            $this->out('feed_server_ids', $form['feed_server_ids']);
        }
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_feed_item_content extends Hm_Handler_Module {
    public function process() {
        $content = '';
        $headers = array();
        list($success, $form) = $this->process_form(array('feed_uid', 'feed_list_path'));
        if ($success) {
            $path = explode('_', $form['feed_list_path']);
            $id = $path[1];
            $feed_items = array();
            $feed_data = Hm_Feed_List::dump($id);
            if ($feed_data) {
                $cache = feed_memcached_fetch($this, $feed_data);
                if (is_array($cache) && count($cache) > 0) {
                    $feed_items = $cache;
                }
                else {
                    $feed = is_news_feed($feed_data['server']);
                    if ($feed && $feed->parsed_data) {
                        $feed_items = $feed->parsed_data;
                        $cache = false;
                    }
                }
            }
            $title = false;
            foreach ($feed_items as $item) {
                if (isset($item['id']) && !isset($item['guid'])) {
                    $item['guid'] = $item['id'];
                    unset($item['id']);
                }
                elseif (array_key_exists('link', $item) && !array_key_exists('guid', $item)) {
                    $item['guid'] = $item['link'];
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
                    if (array_key_exists('title', $item)) {
                        $title = $item['title'];
                    }
                    $headers = $item;
                    unset($headers['title']);
                    $headers = array_merge(array('title' => $title), $headers);
                    break;
                }
            }
            if ($content) {
                feed_memcached_save($this, $feed_data, $feed_items);
                Hm_Feed_Uid_Cache::read($form['feed_uid']);
                $this->out('feed_message_content', $content);
                $this->out('feed_message_headers', $headers);
            }
        }
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_process_add_feed extends Hm_Handler_Module {
    public function process() {
        if (isset($this->request->post['submit_feed'])) {
            $found = false;
            list($success, $form) = $this->process_form(array('new_feed_name', 'new_feed_address'));
            if ($success) {
                $connection_test = address_from_url($form['new_feed_address']);
                if ($con = @fsockopen($connection_test, 80, $errno, $errstr, 2)) {
                    $feed = is_news_feed($form['new_feed_address']);
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

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_load_feeds_from_config extends Hm_Handler_Module {
    public function process() {
        $feeds = $this->user_config->get('feeds', array());
        foreach ($feeds as $index => $feed) {
            Hm_Feed_List::add($feed, $index);
        }
        Hm_Feed_Uid_Cache::load($this->cache->get('feed_read_uids', array(), true));
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_add_feeds_to_page_data extends Hm_Handler_Module {
    public function process() {
        $excluded = false;
        if ($this->get('list_path') == 'unread') {
            $excluded = $this->user_config->get('unread_exclude_feeds_setting', false);
        }
        if ($excluded) {
            return; 
        }
        $feeds = Hm_Feed_List::dump();
        if (!empty($feeds)) {
            $this->out('feeds', $feeds);
        }
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_load_feeds_for_search extends Hm_Handler_Module {
    public function process() {
        foreach (Hm_Feed_List::dump() as $index => $vals) {
            $this->append('data_sources', array('callback' => 'feeds_search_page_content', 'type' => 'feeds', 'name' => $vals['name'], 'id' => $index));
        }
        
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_load_feeds_for_message_list extends Hm_Handler_Module {
    public function process() {
        $callback = false;
        $server_id = false;
        if (array_key_exists('list_path', $this->request->get)) {
            $path = $this->request->get['list_path'];
        }
        else {
            $path = '';
        }
        switch ($path) {
            case 'unread':
                if (!$this->user_config->get('unread_exclude_feeds_setting', false)) {
                    $callback = 'feeds_combined_content_unread';
                }
                break;
            case 'combined_inbox':
                $callback = 'feeds_combined_inbox_content';
                break;
            case 'feeds':
                $callback = 'feeds_combined_content';
                break;
            default:
                if (preg_match("/^feeds_(\d+)$/", $path, $matches)) {
                    $server_id = $matches[1];
                    $callback = 'load_feed_list';
                }
                break;
        }
        if ($callback) {
            foreach (Hm_Feed_List::dump() as $index => $vals) {
                if ($server_id !== false && $index != $server_id) {
                    continue;
                }
                $this->append('data_sources', array('callback' => $callback, 'type' => 'feeds', 'name' => $vals['name'], 'id' => $index));
            }
        }
    }
}

/**
 * @subpackage feeds/handler
 */
class Hm_Handler_save_feeds extends Hm_Handler_Module {
    public function process() {
        $feeds = Hm_Feed_List::dump();
        $this->user_config->set('feeds', $feeds);
        $this->cache->set('feed_read_uids', Hm_Feed_Uid_Cache::dump(), 0, true);
    }
}

/**
 * @subpackage feeds/handler
 */
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

/**
 * @subpackage feeds/output
 */
class Hm_Output_add_feed_dialog extends Hm_Output_Module {
    protected function output() {
        if ($this->format == 'HTML5') {
            $count = count($this->get('feeds', array()));
            $count = sprintf($this->trans('%d configured'), $count);
            return '<div class="feed_server_setup"><div data-target=".feeds_section" class="server_section">'.
                '<img alt="" src="'.Hm_Image_Sources::$rss.'" width="16" height="16" />'.
                ' '.$this->trans('Feeds').' <div class="server_count">'.$count.'</div></div><div class="feeds_section"><form class="add_server" method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<div class="subtitle">'.$this->trans('Add an RSS/ATOM Feed').'</div><table>'.
                '<tr><td><label class="screen_reader" for="new_feed_name">'.$this->trans('Feed name').'</label>'.
                '<input required type="text" id="new_feed_name" name="new_feed_name" class="txt_fld" value="" placeholder="'.$this->trans('Feed name').'" /></td></tr>'.
                '<tr><td><label for="new_feed_address" class="screen_reader">'.$this->trans('Site address or feed URL').'</label>'.
                '<input required type="url" id="new_feed_address" name="new_feed_address" class="txt_fld" placeholder="'.$this->trans('Site address or feed URL').'" value="" /></td></tr>'.
                '<tr><td><input type="submit" value="'.$this->trans('Add').'" name="submit_feed" /></td></tr>'.
                '</table></form>';
        }
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_display_configured_feeds extends Hm_Output_Module {
    protected function output() {
        $res = '';
        if ($this->format == 'HTML5') {
            foreach ($this->get('feeds', array()) as $index => $vals) {
                $res .= '<div class="configured_server">';
                $res .= sprintf('<div class="server_title">%s</div><div title="%s" class="server_subtitle">%s</div>',
                    $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['server']));
                $res .= '<form class="feed_connect" method="POST">';
                $res .= '<input type="hidden" name="feed_id" value="'.$this->html_safe($index).'" />';
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_feed_connect" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="feed_delete" />';
                $res .= '<input type="hidden" value="ajax_feed_debug" name="hm_ajax_hook" />';
                $res .= '</form></div>';
            }
            $res .= '<br class="clear_float" /></div></div>';
        }
        return $res;
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_feed_ids extends Hm_Output_Module {
    protected function output() {
        return '<input type="hidden" class="feed_server_ids" value="'.$this->html_safe(implode(',', array_keys($this->get('feeds', array())))).'" />';
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_filter_feed_item_content extends Hm_Output_Module {
    protected function output() {
        /* TODO: show "cannot find feed item if feed_message_headers is not present */
        if ($this->get('feed_message_headers')) {
            $header_str = '<table class="msg_headers">'.
                '<col class="header_name_col"><col class="header_val_col"></colgroup>';
            foreach ($this->get('feed_message_headers', array()) as $name => $value) {
                if (in_array($name, array('server_id', 'server_name', 'guid', 'id', 'content'), true)) {
                    continue;
                }
                if ($name != 'link' && $name != 'link_alternate' && !strstr($value, ' ') && strlen($value) > 75) {
                    $value = substr($value, 0, 75).'...';
                }
                if ($name == 'title') {
                    $header_str .= '<tr class="header_subject"><th colspan="2">'.$this->html_safe($value).'</td></tr>';
                }
                elseif ($name == 'link' || $name == 'link_alternate') {
                    $header_str .= '<tr class="header_'.$name.'"><th>'.$this->trans($name).'</th><td><a href="'.$this->html_safe($value).'">'.$this->html_safe($value).'</a></td></tr>';
                }
                elseif ($name == 'author' || $name == 'dc:creator' || $name == 'name') {
                    $header_str .= '<tr class="header_from"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                }
                elseif ($name == 'pubdate' || $name == 'dc:date') {
                    $header_str .= '<tr class="header_date"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                }
                else {
                    $header_str .= '<tr><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                }
            }
            $header_str .= '<tr><td class="header_space" colspan="2"></td></tr>';
            $header_str .= '<tr><td colspan="2"></td></tr></table>';
            $this->out('feed_message_content', str_replace(array('<', '>', '&ldquo;'), array(' <', '> ', ' &ldquo;'), $this->get('feed_message_content')));
            $txt = '<div class="msg_text_inner">'.format_msg_html($this->get('feed_message_content')).'</div>';
            $this->out('feed_msg_text', $txt);
            $this->out('feed_msg_headers', $header_str);
        }
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_filter_feed_list_data extends Hm_Output_Module {
    protected function output() {
        $res = array();
        $login_time = false;
        if ($this->get('login_time')) {
            $login_time = $this->get('login_time');
        }
        if ($this->get('feed_list_parent') == 'feeds' ||
            $this->get('feed_list_parent') == 'search' ||
            $this->get('feed_list_parent') == 'combined_inbox') {
            $src_callback = 'feed_source_callback';
        }
        else {
            $src_callback = 'safe_output_callback';
        }
        $show_icons = $this->get('msg_list_icons');
        foreach ($this->get('feed_list_data', array()) as $item) {
            $row_style = 'feeds';
            if (isset($item['id']) && !isset($item['guid'])) {
                $item['guid'] = $item['id'];
                unset($item['id']);
            }
            elseif (isset($item['title']) && !isset($item['guid'])) {
                $item['guid'] = md5($item['title']);
            }
            if (isset($item['guid'])) {
                if (!array_key_exists('title', $item) || !trim($item['title'])) {
                    $item['title'] = $this->trans('[No Subject]');
                }
                $icon = 'rss';
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
                if ($date) {
                    $date = translate_time_str($date, $this);
                }
                $url = '?page=message&uid='.urlencode(md5($item['guid'])).'&list_path=feeds_'.$item['server_id'];
                if ($this->in('feed_list_parent', array('combined_inbox', 'unread', 'feeds', 'search'))) {
                    $url .= '&list_parent='.$this->html_safe($this->get('feed_list_parent', ''));
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
                if (Hm_Feed_Uid_Cache::is_read(md5($item['guid']))) {
                    $flags = array();
                }
                elseif (Hm_Feed_Uid_Cache::is_unread(md5($item['guid']))) {
                    $icon = 'rss_alt';
                    $flags = array('unseen');
                    $row_style .= ' unseen';
                }
                elseif ($timestamp && $login_time && $timestamp <= $login_time) {
                    $flags = array();
                }
                else {
                    $icon = 'rss_alt';
                    $flags = array('unseen');
                }
                $nofrom = '';
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
                    $from = $this->trans('[No From]');
                    $nofrom = ' nofrom';
                }
                else {
                    $from = '';
                }
                if (!$show_icons) {
                    $icon = false;
                }
                $row_style .= ' '.str_replace(' ', '_', $item['server_name']);
                if ($style == 'news') {
                    $res[$id] = message_list_row(array(
                            array('checkbox_callback', $id),
                            array('icon_callback', $flags),
                            array('subject_callback', strip_tags($item['title']), $url, $flags, $icon),
                            array('safe_output_callback', 'source', $item['server_name']),
                            array('safe_output_callback', 'from'.$nofrom, $from),
                            array('date_callback', $date, $timestamp),
                        ),
                        $id,
                        $style,
                        $this,
                        $row_style
                    );
                }
                else {
                    $res[$id] = message_list_row(array(
                            array('checkbox_callback', $id),
                            array($src_callback, 'source', $item['server_name'], $icon, $item['server_id']),
                            array('safe_output_callback', 'from'.$nofrom, $from),
                            array('subject_callback', strip_tags($item['title']), $url, $flags),
                            array('date_callback', $date, $timestamp),
                            array('icon_callback', $flags)
                        ),
                        $id,
                        $style,
                        $this,
                        $row_style
                    );
                }
            }
        }
        $this->out('formatted_message_list', $res);
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_filter_feed_folders extends Hm_Output_Module {
    protected function output() {
        $res = '';
        $folders = $this->get('feed_folders', array());
        if (is_array($folders) && !empty($folders)) {
            $res .= '<li class="menu_feeds"><a class="unread_link" href="?page=message_list&amp;list_path=feeds">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$rss_alt).'" alt="" width="16" height="16" /> ';
            }
            $res .= $this->trans('All').'</a> <span class="unread_feed_count"></span></li>';
            foreach ($this->get('feed_folders') as $id => $folder) {
                $res .= '<li class="feeds_'.$this->html_safe($id).'">'.
                    '<a data-id="feeds_'.$this->html_safe($id).'" href="?page=message_list&list_path=feeds_'.$this->html_safe($id).'">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<img class="account_icon" alt="'.$this->trans('Load Feed').'" src="'.Hm_Image_Sources::$rss.'" width="16" height="16" /> ';
                }
                $res .= $this->html_safe($folder).'</a></li>';
            }
        }
        $res .= '<li class="feeds_add_new"><a href="?page=servers#feeds_section">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$plus).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('Add a feed').'</a></li>';
        if ($res) {
            $this->append('folder_sources', array('feeds_folders', $res));
        }
        return '';
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_display_feeds_status extends Hm_Output_Module {
    protected function output() {
        $res = '';
        foreach ($this->get('feeds', array()) as $index => $vals) {
            $res .= '<tr><td>'.$this->trans('FEED').'</td><td>'.$this->html_safe($vals['name']).'</td><td class="feeds_status_'.$index.'"></td>'.
                '<td class="feeds_detail_'.$index.'"></td></tr>';
        }
        return $res;
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_unread_feeds_included extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('unread_exclude_feeds', $settings) && $settings['unread_exclude_feeds']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        return '<tr class="unread_setting"><td><label for="unread_exclude_feeds">'.$this->trans('Exclude unread feed items').'</label></td>'.
            '<td><input type="checkbox" '.$checked.' value="1" id="unread_exclude_feeds" name="unread_exclude_feeds" /></td></tr>';
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_filter_feed_status_data extends Hm_Output_Module {
    protected function output() {
        if ($this->get('feed_connect_status') == 'Connected') {
            $this->out('feed_status_display', '<span class="online">'.
                $this->trans(ucwords($this->get('feed_connect_status'))).'</span> in '.round($this->get('feed_connect_time'), 3));
        }
        else {
            $this->out('feed_status_display', '<span class="down">'.$this->trans('Down').'</span>');
        }
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_start_feed_settings extends Hm_Output_Module {
    protected function output() {
        return '<tr><td colspan="2" data-target=".feeds_setting" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$rss.'" width="16" height="16" />'.$this->trans('Feed Settings').'</td></tr>';
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_feed_since_setting extends Hm_Output_Module {
    protected function output() {
        $since = false;
        $settings = $this->get('user_settings');
        if (array_key_exists('feed_since', $settings)) {
            $since = $settings['feed_since'];
        }
        return '<tr class="feeds_setting"><td><label for="feed_since">'.$this->trans('Show feed items received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'feed_since', $this).'</td></tr>';
    }
}

/**
 * @subpackage feeds/output
 */
class Hm_Output_feed_limit_setting extends Hm_Output_Module {
    protected function output() {
        $limit = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings');
        if (array_key_exists('feed_limit', $settings)) {
            $limit = $settings['feed_limit'];
        }
        return '<tr class="feeds_setting"><td><label for="feed_limit">'.$this->trans('Max feed items to display').'</label></td>'.
            '<td><input type="text" id="feed_limit" name="feed_limit" size="2" value="'.$this->html_safe($limit).'" /></td></tr>';
    }
}

/**
 * @subpackage feeds/functions
 */
if (!hm_exists('feed_source_callback')) {
function feed_source_callback($vals, $style, $output_mod) {
    if ($vals[2]) {
        $img = '<img alt="'.$output_mod->trans('feed item').'" src="'.Hm_Image_Sources::${$vals[2]}.'" />';
    }
    else {
        $img = '';
    }
    if ($style == 'email') {
        return sprintf('<td class="%s" title="%s"><a href="?page=message_list&list_path=feeds_%s">%s%s</td>',
            $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[3]),
            $img, $output_mod->html_safe($vals[1]));
    }
    elseif ($style == 'news') {
        return sprintf('<div class="%s" title="%s"><a href="?page=message_list&list_path=feeds_%s">%s%s</div>',
            $output_mod->html_safe($vals[0]), $output_mod->html_safe($vals[1]), $output_mod->html_safe($vals[3]),
            $img, $output_mod->html_safe($vals[1]));
    }
}}

/**
 * @subpackage feeds/functions
 */
if (!hm_exists('address_from_url')) {
function address_from_url($str) {
    $res = $str;
    $url_bits = parse_url($str);
    if (isset($url_bits['scheme']) && isset($url_bits['host'])) {
        $res = $url_bits['host'];
    }
    return $res;
}}

/**
 * @subpackage feeds/functions
 */
if (!hm_exists('is_news_feed')) {
function is_news_feed($url, $limit=20) {
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
}}

/**
 * @subpackage feeds/functions
 */
if (!hm_exists('search_for_feeds')) {
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
}}

/**
 * @subpackage feeds/functions
 */
if (!hm_exists('search_feed_item')) {
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
            if (stristr($item[$fld], $terms) !== false) {
                return true;
            }
        }
    }
    return false;
}}

/**
 * @subpackage feeds/functions
 */
if (!hm_exists('feed_memcached_save')) {
function feed_memcached_save($hmod, $feed_data, $data) {
    $key = sprintf('%s%s%s', $feed_data['server'], $feed_data['tls'], $feed_data['port']);
    $hmod->cache->set($key, $data, 500);
}}

/**
 * @subpackage feeds/functions
 */
if (!hm_exists('feed_memcached_fetch')) {
function feed_memcached_fetch($hmod, $feed_data) {
    $key = sprintf('%s%s%s', $feed_data['server'], $feed_data['tls'], $feed_data['port']);
    return $hmod->cache->get($key);
}}
