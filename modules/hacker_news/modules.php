<?php

/**
 * Hacker News modules
 * @package modules
 * @subpackage hackernews
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage hackernews/handler
 */
class Hm_Handler_hacker_news_fields extends Hm_Handler_Module {
    public function process() {
        $this->out('message_list_fields', array(
            array('chkbox_col', false, false),
            array('source_col', 'source', 'Source'),
            array('from_col', 'from', 'From'),
            array('subject_col', 'subject', 'Subject'),
            array('score_col', 'score', 'Score'),
            array('comment_col', 'comment', 'Comments'),
            array('date_col', 'msg_date', 'Date'),
            array('icon_col', false, false)), false);

        $list_type = 'top20';
        if (array_key_exists('list_path', $this->request->get)) {
            $list_type = $this->request->get['list_path'];
        }
        $this->out('list_path', $list_type);
    }
}

/**
 * @subpackage hackernews/handler
 */
class Hm_Handler_hacker_news_data extends Hm_Handler_Module {
    public function process() {
        $list_type = 'top20';
        if (array_key_exists('list_path', $this->request->get)) {
            $list_type = $this->request->get['list_path'];
        }
        if ($list_type == 'top20') {
            $data = array_slice(curl_fetch_json('https://hacker-news.firebaseio.com/v0/topstories.json'), 0, 20);
        }
        elseif ($list_type == 'newest') {
            $data = array();
            $max_id = curl_fetch_json('https://hacker-news.firebaseio.com/v0/maxitem.json');
            for ($i = 0; $i < 20; $i++) {
                $data[] = $max_id - $i;
            }
        }
        $output = array();
        if (is_array($data)) {
            foreach ($data as $id) {
                $item = curl_fetch_json('https://hacker-news.firebaseio.com/v0/item/'.$id.'.json');
                if (is_object($item)) {
                    $output[] = $item;
                }
            }
        }
        $this->out('hacker_news_data', $output);
    }
}

/**
 * @subpackage hackernews/output
 */
class Hm_Output_hacker_news_folders extends Hm_Output_Module {
    protected function output($format) {
        $res = '<li class="menu_top20"><a class="unread_link" href="?page=hacker_news&list_path=top20">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$spreadsheet).
            '" alt="" width="16" height="16" /> '.$this->trans('Top 20').'</a></li>';
        $res .= '<li class="menu_newest"><a class="unread_link" href="?page=hacker_news&list_path=newest">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$spreadsheet).
            '" alt="" width="16" height="16" /> '.$this->trans('Latest').'</a></li>';
        $this->append('folder_sources', 'hacker_news_folders');
        Hm_Page_Cache::add('hacker_news_folders', $res, true);
        return '';
    }
}

/**
 * @subpackage hackernews/output
 */
class Hm_Output_hacker_news_heading extends Hm_Output_Module {
    protected function output($format) {
        return '<div class="content_title">'.$this->trans('Hacker News').'</div>';
    }
}

/**
 * @subpackage hackernews/output
 */
class Hm_Output_hacker_news_table_end extends Hm_Output_Module {
    protected function output($format) {
        return '</tbody></table>';
    }
}

/**
 * @subpackage hackernews/output
 */
class Hm_Output_filter_hacker_news_data extends Hm_Output_Module {
    protected function output($format) {
        $res = array();
        if ($len = count($this->get('hacker_news_data', array()))) {
            $style = $this->get('news_list_style') ? 'news' : 'email';
            if ($this->get('is_mobile')) {
                $style = 'news';
            }
            foreach ($this->get('hacker_news_data', array()) as $index => $item) {
                if (!property_exists($item, 'id')) {
                    continue;
                }
                $url = '';
                $host = 'news.ycombinator.com';
                if ($item->type == 'story') {
                    $url = $item->url;
                    $url_parts = parse_url($item->url);
                    if (array_key_exists('host', $url_parts)) {
                        $host = $url_parts['host'];
                    }
                }
                if (!$url) {
                    $url = sprintf('https://%s/item?id=%d', $host, $item->id);
                }
                $comments = 0;
                if (property_exists($item, 'kids')) {
                    $comments = count($item->kids);
                }
                if ($item->type == 'comment') {
                    $subject = substr(mb_convert_encoding(strip_tags($item->text), 'UTF-8', 'HTML-ENTITIES'), 0, 120);
                }
                else {
                    $subject = $item->title;
                }
                $date = date('r', $item->time);
                if ($style == 'news') {
                    $res[$item->id] = message_list_row(array(
                            array('checkbox_callback', $item->id),
                            array('icon_callback', array()),
                            array('subject_callback', $subject, $url, array()),
                            array('safe_output_callback', 'source', $host),
                            array('safe_output_callback', 'from', $item->by),
                            array('date_callback', human_readable_interval($date), ($len - $index)),
                        ),
                        $item->id,
                        $style,
                        $this
                    );
                }
                else {
                    $res[$item->id] = message_list_row(array(
                            array('checkbox_callback', $item->id),
                            array('safe_output_callback', 'source', $host),
                            array('safe_output_callback', 'from', $item->by),
                            array('subject_callback', $subject, $url, array()),
                            array('score_callback', $item),
                            array('comment_callback', $item),
                            array('date_callback', human_readable_interval($date), ($len - $index)),
                            array('icon_callback', array()),
                        ),
                        $item->id,
                        $style,
                        $this
                    );
                }
            }
        }
        $this->out('formatted_message_list', $res);
    }
}

/**
 * @subpackage hackernews/functions
 */
function comment_callback($vals, $style, $output_mod) {
    $item = $vals[0];
    $comments = 0;
    if (property_exists($item, 'kids')) {
        $comments = count($item->kids);
    }
    return sprintf('<td>%d</td>', $comments);
}

/**
 * @subpackage hackernews/functions
 */
function score_callback($vals, $style, $output_mod) {
    $item = $vals[0];
    $score = 0;
    if (property_exists($item, 'score')) {
        $score = $item->score;
    }
    return sprintf('<td>%d</td>', $score);
}

/**
 * @subpackage hackernews/functions
 */
function curl_fetch_json($url) {
    $curl_handle=curl_init();
    curl_setopt($curl_handle, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 '.
        '(KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36");
    curl_setopt($curl_handle, CURLOPT_URL, $url);
    curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT,15);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
    $buffer = curl_exec($curl_handle);
    curl_close($curl_handle);
    unset($curl_handle);
    return @json_decode($buffer);
}

?>
