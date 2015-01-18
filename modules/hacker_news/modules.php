<?php

if (!defined('DEBUG_MODE')) { die(); }

class Hm_Handler_hacker_news_data extends Hm_Handler_Module {
    public function process() {
        $data = array_slice(curl_fetch_json('https://hacker-news.firebaseio.com/v0/topstories.json'), 0, 20);
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

class Hm_Output_hacker_news_folders extends Hm_Output_Module {
    protected function output($format) {
        $res = '<li class="menu_hacker_news"><a class="unread_link" href="?page=hacker_news">'.
            '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$spreadsheet).
            '" alt="" width="16" height="16" /> '.$this->trans('Top 20').'</a></li>';
        $this->append('folder_sources', 'hacker_news_folders');
        Hm_Page_Cache::add('hacker_news_folders', $res, true);
        return '';
    }
}

class Hm_Output_hacker_news_heading extends Hm_Output_Module {
    protected function output($format) {
        return '<div class="content_title">'.$this->trans('Hacker News').'</div>';
    }
}

class Hm_Output_hacker_news_table_end extends Hm_Output_Module {
    protected function output($format) {
        return '</tbody></table>';
    }
}

class Hm_Output_filter_hacker_news_data extends Hm_Output_Module {
    protected function output($format) {
        $res = array();
        if ($len = count($this->get('hacker_news_data', array()))) {
            foreach ($this->get('hacker_news_data', array()) as $index => $item) {
                $url = '';
                $host = 'news.ycombinator.com';
                if ($item->type == 'story') {
                    $url = $item->url;
                    $url_parts = parse_url($item->url);
                    $host = $url_parts['host'];
                }
                $comments = 0;
                if (property_exists($item, 'kids')) {
                    $comments = count($item->kids);
                }
                $date = date('r', $item->time);
                $res[$item->id] = message_list_row($item->title.' ['.$comments.']', human_readable_interval($date), ($len - $index),
                    $item->by, $host, $item->id, array(), 'email', $url, $this, true);
            }
        }
        $this->out('formatted_message_list', $res);
    }
}

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
