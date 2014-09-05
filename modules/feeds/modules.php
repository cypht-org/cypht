<?php

if (!defined('DEBUG_MODE')) { die(); }

require 'modules/feeds/hm-feed.php';

class Hm_Handler_process_feed_limit_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'feed_limit'));
        if ($success) {
            if ($form['feed_limit'] > MAX_PER_SOURCE || $form['feed_limit'] < 0) {
                $limit = DEFAULT_PER_SOURCE;
            }
            else {
                $limit = $form['feed_limit'];
            }
            $data['new_user_settings']['feed_limit'] = $limit;
        }
        else {
            $data['user_settings']['feed_limit'] = $this->user_config->get('feed_limit', DEFAULT_PER_SOURCE);
        }
        return $data;
    }
}

class Hm_Handler_process_feed_since_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings', 'feed_since'));
        if ($success) {
            $data['new_user_settings']['feed_since'] = process_since_argument($form['feed_since'], true);
        }
        else {
            $data['user_settings']['feed_since'] = $this->user_config->get('feed_since', false);
        }
        return $data;
    }
}

class Hm_Handler_process_unread_feeds_setting extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('save_settings'));
        if ($success) {
            if (array_key_exists('unread_exclude_feeds', $this->request->post)) {
                $data['new_user_settings']['unread_exclude_feeds'] = true;
            }
            else {
                $data['new_user_settings']['unread_exclude_feeds'] = false;
            }
        }
        else {
            $data['user_settings']['unread_exclude_feeds'] = $this->user_config->get('unread_exclude_feeds', false);
        }
        return $data;
    }
}

class Hm_Handler_feed_connect extends Hm_Handler_Module {
    public function process($data) {
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
        return $data;
    }
}
class Hm_Handler_delete_feed extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['delete_feed'])) {
            list($success, $form) = $this->process_form(array('feed_id'));
            if ($success) {
                $res = Hm_Feed_List::del($form['feed_id']);
                if ($res) {
                    $data['deleted_server_id'] = $form['feed_id'];
                    $data['reload_folders'] = true;
                    Hm_Msgs::add('Feed deleted');
                    $this->session->record_unsaved('Feed deleted');
                }
            }
            else {
                $data['old_form'] = $form;
            }
        }
        return $data;
    }
}
class Hm_Handler_feed_status extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('feed_server_ids'));
        if ($success) {
            $ids = explode(',', $form['feed_server_ids']);
            foreach ($ids as $id) {
                $start_time = microtime(true);
                $feed_data = Hm_Feed_List::dump($id);
                if ($feed_data) {
                    $feed = is_feed($feed_data['server']);
                    if ($feed && $feed->parsed_data) {
                        $data['feed_connect_time'] = microtime(true) - $start_time;
                        $data['feed_connect_status'] = 'Connected';
                        $data['feed_status_server_id'] = $id;
                    }
                }
            }
        }
        return $data;
    }
}

class Hm_Handler_feed_message_action extends Hm_Handler_Module {
    public function process($data) {

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
        return $data;
    }
}

class Hm_Handler_feed_list_content extends Hm_Handler_Module {
    public function process($data) {
        list($success, $form) = $this->process_form(array('feed_server_ids'));
        if ($success) {
            $ids = explode(',', $form['feed_server_ids']);
            $res = array();
            $unread_only = false;
            $login_time = $this->session->get('login_time', false);
            if ($login_time) {
                $data['login_time'] = $login_time;
            }
            if (array_key_exists('list_path', $data) && $data['list_path'] == 'unread') {
                $limit = $this->user_config->get('unread_per_source_setting', DEFAULT_PER_SOURCE);
                $date = process_since_argument($this->user_config->get('unread_since_setting', DEFAULT_SINCE));
                $unread_only = true;
                $cutoff_timestamp = strtotime($date);
                if ($login_time && $login_time > $cutoff_timestamp) {
                    $cutoff_timestamp = $login_time;
                }
            }
            elseif (array_key_exists('list_path', $data) && $data['list_path'] == 'combined_inbox') {
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
                            else {
                                $item['server_id'] = $id;
                                $item['server_name'] = $feed_data['name'];
                                $res[] = $item;
                            }
                        }
                    }
                }
            }
            $data['feed_list_data'] = $res;
            if (isset($this->request->get['list_path'])) {
                $data['feed_list_parent'] = $this->request->get['list_path'];
            }
            $data['feed_server_ids'] = $form['feed_server_ids'];
        }
        return $data;
    }
}

class Hm_Handler_feed_item_content extends Hm_Handler_Module {
    public function process($data) {
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
                        if (isset($item['guid']) && md5($item['guid']) == $form['feed_uid']) {
                            if (isset($item['description'])) {
                                $content = $item['description'];
                                unset($item['description']);
                                $headers = $item;
                                $headers['source'] = $feed_data['name'];
                            }
                        }
                    }
                }
            }
            if ($content) {
                Hm_Feed_Seen_Cache::add($form['feed_uid']);
                $data['feed_message_content'] = $content;
                $data['feed_message_headers'] = $headers;
            }
        }
        return $data;
    }
}

class Hm_Handler_process_add_feed extends Hm_Handler_Module {
    public function process($data) {
        if (isset($this->request->post['submit_feed'])) {
            list($success, $form) = $this->process_form(array('new_feed_name', 'new_feed_address'));
            if ($success) {
                $found = false;
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
                $data['reload_folders'] = true;
                Hm_Feed_List::add(array(
                    'name' => $form['new_feed_name'],
                    'server' => $href,
                    'tls' => false,
                    'port' => 80
                ));
                $this->session->record_unsaved('Feed added');
            }
        }
        return $data;
    }
}

class Hm_Handler_load_feeds_from_config extends Hm_Handler_Module {
    public function process($data) {
        $feeds = $this->user_config->get('feeds', array());
        foreach ($feeds as $index => $feed) {
            Hm_Feed_List::add($feed, $index);
        }
        Hm_Feed_Seen_Cache::load($this->session->get('feed_read_uids', array()));
        return $data;
    }
}

class Hm_Handler_add_feeds_to_page_data extends Hm_Handler_Module {
    public function process($data) {
        $excluded = false;
        if (isset($data['list_path']) && $data['list_path'] == 'unread') {
            $excluded = $this->user_config->get('unread_exclude_feeds', false);
        }
        if ($excluded) {
            return $data;
        }
        $feeds = Hm_Feed_List::dump();
        if (!empty($feeds)) {
            $data['feeds'] = $feeds;
            $data['folder_sources'][] = 'feeds_folders';
        }
        return $data;
    }
}

class Hm_Handler_save_feeds extends Hm_Handler_Module {
    public function process($data) {
        $feeds = Hm_Feed_List::dump();
        $this->user_config->set('feeds', $feeds);
        $this->session->set('feed_read_uids', Hm_Feed_Seen_Cache::dump());
        return $data;
    }
}

class Hm_Handler_load_feed_folders extends Hm_Handler_Module {
    public function process($data) {
        $feeds = Hm_Feed_List::dump();
        $folders = array();
        if (!empty($feeds)) {
            foreach ($feeds as $id => $feed) {
                $folders[$id] = $feed['name'];
            }
        }
        $data['feed_folders'] = $folders;
        return $data;
    }
}

class Hm_Output_add_feed_dialog extends Hm_Output_Module {
    protected function output($input, $format) {
        if ($format == 'HTML5') {
            return '<div class="imap_server_setup"><div class="content_title">Feeds</div><form class="add_server" method="POST">'.
                '<input type="hidden" name="hm_nonce" value="'.$this->build_nonce('add_feed').'"/>'.
                '<div class="subtitle">Add an RSS/ATOM Feed</div><table>'.
                '<tr><td colspan="2"><input type="text" name="new_feed_name" class="txt_fld" value="" placeholder="Feed name" /></td></tr>'.
                '<tr><td colspan="2"><input type="text" name="new_feed_address" class="txt_fld" placeholder="Site address or feed URL" value=""/></td></tr>'.
                '<tr><td align="right"><input type="submit" value="Add" name="submit_feed" /></td></tr>'.
                '</table></form>';
        }
    }
}

class Hm_Output_display_configured_feeds extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if ($format == 'HTML5') {
            if (isset($input['feeds'])) {
                foreach ($input['feeds'] as $index => $vals) {
                    $res .= '<div class="configured_server">';
                    $res .= sprintf('<div class="server_title">%s</div><div title="%s" class="server_subtitle">%s</div>', $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['server']));
                    $res .= '<form class="feed_connect" method="POST">';
                    $res .= '<input type="hidden" name="feed_id" value="'.$this->html_safe($index).'" />';
                    $res .= '<input type="submit" value="Test" class="test_feed_connect" />';
                    $res .= '<input type="submit" value="Delete" class="feed_delete" />';
                    $res .= '<input type="hidden" value="ajax_feed_debug" name="hm_ajax_hook" />';
                    $res .= '</form></div>';
                }
            }
        }
        return $res;
    }
}

class Hm_Output_feed_ids extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['feeds'])) {
            return '<input type="hidden" class="feed_server_ids" value="'.$this->html_safe(implode(',', array_keys($input['feeds']))).'" />';
        }
    }
}

class Hm_Output_filter_feed_item_content extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['feed_message_content'])) {
            $header_str = '<table class="msg_headers" cellspacing="0" cellpadding="0">'.
                '<col class="header_name_col"><col class="header_val_col"></colgroup>';
            foreach ($input['feed_message_headers'] as $name => $value) {
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
            $input['feed_message_content'] = str_replace(array('<', '>', '&ldquo;'), array(' <', '> ', ' &ldquo;'), $input['feed_message_content']);
            $txt = '<div class="msg_text_inner">'.format_msg_html($input['feed_message_content']).'</div>';
            unset($input['feed_message_content']);
            unset($input['feed_message_headers']);
            $input['feed_msg_text'] = $txt;
            $input['feed_msg_headers'] = $header_str;
        }
        return $input;
    }
}

class Hm_Output_filter_feed_list_data extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = array();
        $login_time = false;
        if (isset($input['login_time'])) {
            $login_time = $input['login_time'];
        }
        if (isset($input['feed_list_data'])) {
            foreach ($input['feed_list_data'] as $item) {
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
                    if (isset($input['feed_list_parent']) && $input['feed_list_parent'] == 'combined_inbox') {
                        $url .= '&list_parent=combined_inbox';
                    }
                    elseif (isset($input['feed_list_parent']) && $input['feed_list_parent'] == 'unread') {
                        $url .= '&list_parent=unread';
                    }
                    elseif (isset($input['feed_list_parent']) && $input['feed_list_parent'] == 'feeds') {
                        $url .= '&list_parent=feeds';
                    }
                    else {
                        $url .= '&list_parent=feeds_'.$item['server_id'];
                    }
                    if (isset($input['news_list_style'])) {
                        $style = 'news';
                    }
                    else {
                        $style = 'email';
                    }
                    if ($input['is_mobile']) {
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
                    elseif (isset($item['dc:creator'])) {
                        $from = display_value('dc:creator', $item, 'from');
                    }
                    else {
                        $from = '[No From]';
                    }
                    $res[$id] = message_list_row($item['title'], $date, $timestamp, $from, $item['server_name'], $id, $flags, $style, $url, $this);
                }
            }
            unset($input['feed_list_data']);
        }
        $input['formatted_feed_data'] = $res;
        return $input;
    }
}

class Hm_Output_filter_feed_folders extends Hm_Output_Module {
    protected function output($input, $format) {
        $res = '';
        if (isset($input['feed_folders'])) {
            $res .= '<li class="menu_feeds"><a class="unread_link" href="?page=message_list&amp;list_path=feeds">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$globe).'" alt="" width="16" height="16" /> '.$this->trans('All').'</a> <span class="unread_feed_count"></span></li>';
            foreach ($input['feed_folders'] as $id => $folder) {
                $res .= '<li class="feeds_'.$this->html_safe($id).'">'.
                    '<a href="?page=message_list&list_path=feeds_'.$this->html_safe($id).'">'.
                    '<img class="account_icon" alt="Toggle folder" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> '.
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
        if (isset($input['feeds']) && !empty($input['feeds'])) {
            foreach ($input['feeds'] as $index => $vals) {
                $res .= '<tr><td>FEED</td><td>'.$vals['name'].'</td><td class="feeds_status_'.$index.'"></td>'.
                    '<td class="feeds_detail_'.$index.'"></td></tr>';
            }
        }
        return $res;
    }
}

class Hm_Output_unread_feeds_included extends Hm_Output_Module {
    protected function output($input, $format) {
        if (array_key_exists('user_settings', $input) && array_key_exists('unread_exclude_feeds', $input['user_settings']) && $input['user_settings']['unread_exclude_feeds']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        return '<tr><td>Exclude unread feed items</td><td><input type="checkbox" '.$checked.' value="1" name="unread_exclude_feeds" /></td></tr>';
    }
}

class Hm_Output_filter_feed_status_data extends Hm_Output_Module {
    protected function output($input, $format) {
        if (isset($input['feed_connect_status']) && $input['feed_connect_status'] == 'Connected') {
            $input['feed_status_display'] = '<span class="online">'.
                $this->html_safe(ucwords($input['feed_connect_status'])).'</span> in '.round($input['feed_connect_time'],3);
            $input['feed_detail_display'] = '';
        }
        else {
            $input['feed_status_display'] = '<span class="down">Down</span>';
            $input['feed_detail_display'] = '';
        }
        return $input;
    }
}

class Hm_Output_start_feed_settings extends Hm_Output_Module {
    protected function output($input, $format) {
        return '<tr><td colspan="2" class="settings_subtitle"><br /><img src="'.Hm_Image_Sources::$env_closed.'" />FEED Settings</td></tr>';
    }
}

class Hm_Output_feed_since_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $since = false;
        if (array_key_exists('user_settings', $input) && array_key_exists('feed_since', $input['user_settings'])) {
            $since = $input['user_settings']['feed_since'];
        }
        return '<tr><td>Show feed items received since</td><td>'.message_since_dropdown($since, 'feed_since').'</td></tr>';
    }
}

class Hm_Output_feed_limit_setting extends Hm_Output_Module {
    protected function output($input, $format) {
        $limit = DEFAULT_PER_SOURCE;
        if (array_key_exists('user_settings', $input) && array_key_exists('feed_limit', $input['user_settings'])) {
            $limit = $input['user_settings']['feed_limit'];
        }
        return '<tr><td>Max feed items to display</td><td><input type="text" name="feed_limit" size="2" value="'.$this->html_safe($limit).'" /></td></tr>';
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

?>
