<?php

/**
 * Contact modules
 * @package modules
 * @subpackage history
 */

/**
 * @subpackage history/handler
 */
class Hm_Handler_history_record_feed_message extends Hm_Handler_Module {
    public function process() {
        $headers = $this->get('feed_message_headers', array());
        if (count($headers) == 0) {
            return;
        }
        $history = $this->session->get('msg_history', array());
        $url = sprintf('?page=message&uid=%s&list_path=%s', $this->request->post['feed_uid'],
            $this->request->post['feed_list_path']);
        if (array_key_exists($url, $history)) {
            return;
        }
        $path = explode('_', $this->request->post['feed_list_path']);
        $feed_data = Hm_Feed_List::dump($path[1]);
        $id = sprintf('feeds_%s_%s', $path[1], $this->request->post['feed_uid']);
        $from = '';
        foreach (array('author', 'cd:creator', 'name') as $v) {
            if (array_key_exists($v, $headers)) {
                $from = $headers[$v];
                break;
            }
        }
        $date = '';
        foreach (array('pubdate', 'dc:date') as $v) {
            if (array_key_exists($v, $headers)) {
                $date = $headers[$v];
                break;
            }
        }
        $history[$url] = array(
            'source' => $feed_data['name'],
            'subject' => $headers['title'],
            'id' => $id,
            'from' => $from,
            'date' => $date,
            'type' => 'feed'
        );
        $this->session->set('msg_history', $history);
    }
}

/**
 * @subpackage history/handler
 */
class Hm_Handler_history_record_pop3_message extends Hm_Handler_Module {
    public function process() {
        $headers = lc_headers($this->get('pop3_message_headers', array()));
        if (count($headers) == 0) {
            return;
        }
        $history = $this->session->get('msg_history', array());
        $url = sprintf('?page=message&uid=%s&list_path=%s', $this->request->post['pop3_uid'],
            $this->request->post['pop3_list_path']);
        if (array_key_exists($url, $history)) {
            return;
        }
        $path = explode('_', $this->request->post['pop3_list_path']);
        $pop3_data = Hm_POP3_List::dump($path[1]);
        $id = sprintf('pop3_%s_%s', $path[1], $this->request->post['pop3_uid']);
        $date = '';
        if (array_key_exists('date', $headers)) {
            $date = trim($headers['date']);
        }
        if (!$date && array_key_exists('delivery-date', $headers)) {
            $date = trim($headers['delivery-date']);
        }
        $from = '';
        if (array_key_exists('from', $headers)) {
            $from = trim($headers['from']);
        }
        $subject = '';
        if (array_key_exists('subject', $headers)) {
            $subject = trim($headers['subject']);
        }
        $history[$url] = array(
            'source' => $pop3_data['name'],
            'id' => $id,
            'subject' => $subject,
            'from' => $from,
            'date' => $date,
            'type' => 'pop3'
        );
        $this->session->set('msg_history', $history);
    }
}

/**
 * @subpackage history/output
 */
class Hm_Github_Output_Module extends Hm_Output_Module { protected function output() { return ''; } }

/**
 * @subpackage history/handler
 */
class Hm_Handler_history_record_github_message extends Hm_Handler_Module {
    public function process() {
        $data = $this->get('github_event_detail', array());
        if (count($data) == 0) {
            return;
        }
        $url = sprintf('?page=message&uid=%s&list_path=%s', $this->request->post['github_uid'],
            $this->request->post['list_path']);
        $history = $this->session->get('msg_history', array());
        if (array_key_exists($url, $history)) {
            return;
        } 
        $stub = new Hm_Github_Output_Module(array(), array());
        $subject = build_github_subject($data, $stub);
        $date = '';
        $id = sprintf('github_%s', $this->request->post['github_uid']);
        if (array_key_exists('created_at', $data)) {
            $date = date('r', strtotime($data['created_at']));
        }
        $from = '';
        if (array_key_exists('actor', $data) && array_key_exists('login', $data['actor'])) {
            $from = $data['actor']['login'];
        }
        $history[$url] = array(
            'source' => substr($this->request->post['list_path'], 7),
            'subject' => $subject,
            'date' => $date,
            'id' => $id,
            'from' => $from,
            'type' => 'github'
        );
        $this->session->set('msg_history', $history);
    }
}

/**
 * @subpackage history/handler
 */
class Hm_Handler_history_record_wp_message extends Hm_Handler_Module {
    public function process() {
        $data = $this->get('wp_notice_details');
        if (count($data) == 0) {
            return;
        }
        $url = sprintf('?page=message&list_path=wp_notifications&uid=%s', $this->request->post['wp_uid']);
        $history = $this->session->get('msg_history', array());
        $id = sprintf('wp_%s', $this->request->post['wp_uid']);
        if (array_key_exists($url, $history)) {
            return;
        } 
        $history[$url] = array(
            'source' => 'WordPress.com',
            'date' => date('r', $data['notes'][0]['timestamp']),
            'subject' => $data['notes'][0]['subject']['text'],
            'id' => $id,
            'from' => '',
            'type' => 'wordpress',
        );
        $this->session->set('msg_history', $history);
    }
}

/**
 * @subpackage history/handler
 */
class Hm_Handler_history_record_imap_message extends Hm_Handler_Module {
    public function process() {
        $headers = lc_headers($this->get('msg_headers', array()));
        if (count($headers) == 0) {
            return;
        }
        if (array_key_exists('imap_prefetch', $this->request->post) && $this->request->post['imap_prefetch']) {
            return;
        }
        $history = $this->session->get('msg_history', array());
        $list_path = sprintf('imap_%s_%s', $this->request->post['imap_server_id'], $this->request->post['folder']);
        $url = sprintf('?page=message&uid=%s&list_path=%s', $this->request->post['imap_msg_uid'], $list_path);
        $id = sprintf('imap_%s_%s_%s', $this->request->post['imap_server_id'],
            $this->request->post['imap_msg_uid'], $this->request->post['folder']);
        if (array_key_exists($url, $history)) {
            return;
        }
        $date = '';
        if (array_key_exists('date', $headers) && $headers['date']) {
            $date = $headers['date'];
        }
        $subject = '';
        if (array_key_exists('subject', $headers) && $headers['subject']) {
            $subject = $headers['subject'];
        }
        $from = '';
        if (array_key_exists('from', $headers) && $headers['from']) {
            $from = format_imap_from_fld($headers['from']);
        }

        $history[$url] = array(
            'source' => Hm_IMAP_List::dump($this->request->post['imap_server_id'])['name'],
            'subject' => $subject,
            'id' => $id,
            'from' => $from,
            'date' => $date,
            'type' => 'imap'
        );
        $this->session->set('msg_history', $history);
    }
}

/**
 * @subpackage history/handler
 */
class Hm_Handler_load_message_history extends Hm_Handler_Module {
    public function process() {
        $this->out('msg_history', $this->session->get('msg_history', array()));
    }
}

/**
 * @subpackage history/output
 */
class Hm_Output_history_page_link extends Hm_Output_Module {
    protected function output() {
        $res = '<li class="menu_history"><a class="unread_link" href="?page=history">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$history).'" alt="" width="16" height="16" /> ';
        }
        $res .= $this->trans('History').'</a></li>';
        if ($this->format == 'HTML5') {
            return $res;
        }
        $this->concat('formatted_folder_list', $res);
    }
}

/**
 * @subpackage history/output
 */
class Hm_Output_history_heading  extends Hm_Output_Module {
    protected function output() {
        $res = '<div class="content_title">'.$this->trans('Message history').'</div>'.
            '<div class="history_content"><table class="message_table">';
        if (!$this->get('is_mobile')) {
            $res .= '<colgroup><col class="source_col"><col class="from_col"><col '.
                'class="subject_col"><col class="date_col"></colgroup><thead></thead>';
        }
        $res .= '<tbody>';
        return $res;
    }
}

/**
 * @subpackage history/output
 */
class Hm_Output_history_content  extends Hm_Output_Module {
    protected function output() {
        $style = $this->get('news_list_style') ? 'news' : 'email';
        if ($this->get('is_mobile')) {
            $style = 'news';
        }
        $res = '';
        $data = $this->get('msg_history', array());
        foreach ($data as $url => $row) {
            $from_class = 'from';
            if (!$row['from']) {
                $row['from'] = $this->trans('[No From]');
                $from_class = 'nofrom';
            }
            if (!$row['subject']) {
                $row['subject'] = sprintf('[%s]', $this->trans('No subject'));
            }
            if (!$row['date']) {
                $row['date'] = sprintf('[%s]', $this->trans('No date'));
                $ts = 0;
            }
            else {
                $ts = strtotime($row['date']);
            }
            switch ($row['type']) {
                case 'github':
                    $icon = 'code';
                    break;
                case 'wordpress':
                    $icon = 'w';
                    break;
                case 'feed':
                    $icon = 'rss';
                    break;
                default:
                    $icon = 'env_closed';
                    break;
            }
            if ($style == 'news') {
                $data = message_list_row(array(
                        array('subject_callback', $row['subject'], $url, array(), ''),
                        array('safe_output_callback', 'source', $row['type'].' - '),
                        array('safe_output_callback', 'source', $row['source']),
                        array('safe_output_callback', $from_class, $row['from']),
                        array('date_callback', human_readable_interval($row['date']), $ts),
                    ),
                    $row['id'],
                    $style,
                    $this
                );
            }
            else {
                $data = message_list_row(array(
                        array('safe_output_callback', 'source', $row['type'].'-'.$row['source'], $icon),
                        array('safe_output_callback', $from_class, $row['from']),
                        array('subject_callback', $row['subject'], $url, array(), ''),
                        array('date_callback', human_readable_interval($row['date']), $ts),
                    ),
                    $row['id'],
                    $style,
                    $this
                );
            }
            $res .= $data[0];
        }
        return $res;
    }
}

/**
 * @subpackage history/output
 */
class Hm_Output_history_footer  extends Hm_Output_Module {
    protected function output() {
        $res = '</tbody></table></div>';
        if (count($this->get('msg_history', array())) == 0) {
            $res .= '<div class="empty_list">'.$this->trans('So alone').'</div>';
        }
        return $res;
    }
}
