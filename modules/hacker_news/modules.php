<?php

/**
 * Hacker News modules
 * @package modules
 * @subpackage hackernews
 */

if (!defined('DEBUG_MODE')) { die(); }

require APP_PATH.'third_party/simple_html_dom.php';

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

function hn_parse_subtext($text) {
    if (preg_match("/<span class=\"score\" id=\"score_(\d+)\">(\d+) point(s|)<\/span> by <a href=\"user\?id=([^\"]+)\">[^<]+<\/a> <a href=\"item\?id=(\d+)\">([^<]+)<\/a>  \| <a href=\"item\?id=\d+\">(.+)<\/a>/", $text, $matches)) {
        return array(
            'id' => $matches[1],
            'votes' => $matches[2],
            'user' => $matches[4],
            'timestamp' => strtotime(sprintf('-%s', trim(str_replace('ago', '', $matches[6])))),
            'comment' => (int) trim(str_replace('comments', '', $matches[7]))
        );
    }
    else {
        elog($text);
    }
    return array();
}
/**
 * @subpackage hackernews/handler
 */
class Hm_Handler_hacker_news_data extends Hm_Handler_Module {
    public function process() {
        $list_type = 'top20';
        $html = false;
        if (array_key_exists('list_path', $this->request->get)) {
            $list_type = $this->request->get['list_path'];
        }
        if ($list_type == 'top20') {
            $html = file_get_html('https://news.ycombinator.com/');
        }
        elseif ($list_type == 'newest') {
            $html = file_get_html('https://news.ycombinator.com/newest');
        }
        $news = array();
        $title = false;
        $url = false;
        if ($html) {
            foreach ($html->find('.title a, .subtext') as $index => $el) {
                if ($title !== false) {
                    $subtext = hn_parse_subtext($el->innertext);
                    $news[] = array_merge(array('title' => trim($title), 'url' => $url), $subtext);
                    $title = false;
                    $url = false;
                    continue;
                }
                $title = $el->plaintext;
                $url = $el->href;
            }
        }
        elog($news);
        $this->out('hacker_news_data', $news);
    }
}

/**
 * @subpackage hackernews/output
 */
class Hm_Output_hacker_news_folders extends Hm_Output_Module {
    protected function output() {
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
    protected function output() {
        return '<div class="content_title">'.$this->trans('Hacker News').'</div>';
    }
}

/**
 * @subpackage hackernews/output
 */
class Hm_Output_hacker_news_table_end extends Hm_Output_Module {
    protected function output() {
        return '</tbody></table>';
    }
}

/**
 * @subpackage hackernews/output
 */
class Hm_Output_filter_hacker_news_data extends Hm_Output_Module {
    protected function output() {
        $res = array();
        if ($len = count($this->get('hacker_news_data', array()))) {
            $style = $this->get('news_list_style') ? 'news' : 'email';
            if ($this->get('is_mobile')) {
                $style = 'news';
            }
            foreach ($this->get('hacker_news_data', array()) as $index => $item) {
                if (!array_key_exists('id', $item)) {
                    continue;
                }
                $url = $item['url'];
                $parts = parse_url($url);
                $host = '';
                if (array_key_exists('host', $parts)) {
                    $host = $parts['host'];
                }
                $user = array_key_exists('user', $item) ? $item['user'] : '';
                $timestamp = array_key_exists('timestamp', $item) ? $item['timestamp'] : 0;
                $comments = array_key_exists('comment', $item) ? $item['comment'] : 0;
                $subject = $this->html_safe(html_entity_decode($item['title']));
                $date = $timestamp != 0 ? date('r', $timestamp) : date('r');
                $votes = array_key_exists('votes', $item) ? $item['votes'] : 0;

                if ($style == 'news') {
                    $res[$item['id']] = message_list_row(array(
                            array('checkbox_callback', $item['id']),
                            array('icon_callback', array()),
                            array('subject_callback', $subject, $url, array()),
                            array('safe_output_callback', 'source', $host),
                            array('safe_output_callback', 'from', $user),
                            array('date_callback', human_readable_interval($date), $timestamp)
                        ),
                        $item['id'],
                        $style,
                        $this
                    );
                }
                else {
                    $res[$item['id']] = message_list_row(array(
                            array('checkbox_callback', $item['id']),
                            array('safe_output_callback', 'source', $host),
                            array('safe_output_callback', 'from', $user),
                            array('subject_callback', $subject, $url, array()),
                            array('score_callback', $votes),
                            array('comment_callback', $comments),
                            array('date_callback', human_readable_interval($date), $timestamp),
                            array('icon_callback', array()),
                        ),
                        $item['id'],
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
function comment_callback($comments, $style, $output_mod) {
    return sprintf('<td>%d</td>', $comments[0]);
}

/**
 * @subpackage hackernews/functions
 */
function score_callback($score, $style, $output_mod) {
    return sprintf('<td>%d</td>', $score[0]);
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
