<?php

/**
 * Feeds modules
 * @package modules
 * @subpackage feeds
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Manage a list of feeds
 * @subpackage feeds/lib
 */
class Hm_Feed_List {

    use Hm_Server_List;

    /*
     * Connect to an RSS/ATOM feed
     * @param int $id server id
     * @param array $server server details
     * @param string $user username
     * @param string $pass password
     * @param array $cache server cache
     * @return bool
     */
    public static function service_connect($id, $server, $user, $pass, $cache=false) {
        self::$server_list[$id]['object'] = new Hm_Feed();
        return self::$server_list[$id]['object'];
    }

    /**
     * Get a server cache
     * @param object $session session object
     * @param int $id server id
     * @return bool
     */
    public static function get_cache($session, $id) {
        return false;
    }
}

/**
 * Used to cahce "read" feed item ids
 * @subpackage feeds/lib
 */
class Hm_Feed_Uid_Cache {
    use Hm_Uid_Cache;
}

/**
 * Connect to and parse RSS/ATOM feeds
 * @subpackage feeds/lib
 */
class Hm_Feed {

    var $url;
    var $id;
    var $xml_data;
    var $parsed_data;
    var $depth;
    var $type;
    var $limit;
    var $heading_block;
    var $data_block;
    var $update_cache;
    var $collect;
    var $item_count;
    var $refresh_cache;
    var $init_cache;
    var $cache_limit;
    var $sort;

    /**
     * Setup defaults
     * @return void
     */
    function __construct() {
        $this->sort = true;
        $this->limit = 20;
        $this->cache_limit = 0;
        $this->status_code = false;
        $this->url = false;
        $this->xml_data = false;
        $this->id = 0;
        $this->parsed_data = array();
        $this->depth = 0;
        $this->feed_type = 'rss';
        $this->heading_block = false;
        $this->data_block = false;
        $this->collect = false;
        $this->refresh_cache = false;
        $this->update_cache = false;
        $this->init_cache = false;
        $this->item_count = 0;
    }

    /**
     * Get data from a feed url
     * @param string $url location of the feed
     * @return string
     */
    function get_feed_data($url) {
        $buffer = '';
        if (!preg_match("?^http(|s)://?", ltrim($url))) {
            $url = 'http://'.ltrim($url);
        }
        if (function_exists('curl_setopt')) {
            $type = 'curl';
        }
        else {
            $type = 'file';
        }
        switch ($type) {
            case 'curl':
                $curl_handle=curl_init();
                curl_setopt($curl_handle, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36");
                curl_setopt($curl_handle, CURLOPT_URL, $url);
                curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT,15);
                curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER,1);
                curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
                $buffer = trim(curl_exec($curl_handle));
                $this->status_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
                if ($this->status_code !== false && $this->status_code !== 200) {
                    Hm_Debug::add(sprintf('BAD STATUS CODE %s from url %s', $this->status_code, $url));
                }
                curl_close($curl_handle);
                unset($curl_handle);
                break;
            case 'file':
                $buffer = file_get_contents($url); 
                break;
        }
        $this->xml_data = $buffer;
        return $buffer;
    }

    /**
     * Sort feed items by date
     * @param array $a first item
     * @param array $b second item
     * return int
     */
    function sort_by_time($a, $b) {
        if (isset($a['dc:date']) && isset($b['dc:date'])) {
            $adate = $a['dc:date'];
            $bdate = $b['dc:date'];
        }
        elseif (isset($a['pubdate']) && isset($b['pubdate'])) {
            $adate = $a['pubdate'];
            $bdate = $b['pubdate'];
        }
        else {
            return 0;
        }
        $time1 = strtotime($adate);
        $time2 = strtotime($bdate);
        if ($time1 == $time2) {
            return 0;
        }
        elseif ($time1 < $time2) {
            return 1;
        }
        else {
            return -1;
        }
    }

    /**
     * Sort a list using sort_by_time
     * @return void
     */
    function sort_parsed_data() {
        $data = $this->parsed_data;
        usort($data, array($this, 'sort_by_time'));
        $final_list = array();
        $i = 1;
        foreach ($data as $vals) {
            $final_list[] = $vals;
            if ($i == $this->limit) {
                break;
            }
            $i++;
        }
        $this->parsed_data = $final_list;
    }

    /**
     * Parse feed content
     * @param string $url feed location
     * @return bool
     */
    function parse_feed($url) {
        $this->get_feed_data($url);
        if (!empty($this->parsed_data)) {
            return true;
        }
        if (preg_match('/<feed .+atom/i', $this->xml_data)) {
            $this->feed_type = 'atom';
        }
        $xml_parser = xml_parser_create('UTF-8');
        xml_set_object($xml_parser, $this);
        if ($this->feed_type == 'atom' || $this->feed_type == 'rss') {
            xml_set_element_handler($xml_parser, $this->feed_type.'_start_element', $this->feed_type.'_end_element');
            xml_set_character_data_handler($xml_parser, $this->feed_type.'_character_data');
            if  (xml_parse($xml_parser, $this->xml_data)) {
                xml_parser_free($xml_parser);
                if ($this->sort) {
                    $this->sort_parsed_data();
                }
                return true;
            }
            else {
                Hm_Debug::add(sprintf('XML Parse error: %s', xml_error_string(xml_get_error_code($xml_parser))));
                Hm_Debug::add($this->xml_data);
                return false; 
            }
        }
        else {
            return false;
        }
    }

    /**
     * ATOM specific parsing
     * @param object $parser xml parser
     * @param string $tagname xml tag name
     * @param array $attrs tag attributes
     */
    function atom_start_element($parser, $tagname, $attrs) {
        if ($tagname == 'FEED') {
            $this->heading_block = true;
        }
        if ($tagname == 'ENTRY') {
            $this->heading_block = false;
            $this->item_count++;
            $this->data_block = true;
        }
        if ($this->data_block) {
            switch ($tagname) {
                case 'TITLE':
                case 'SUMMARY':
                case 'CONTENT':
                case 'GUID':
                case 'UPDATED':
                case 'MODIFIED':
                case 'ID':
                case 'NAME':
                    $this->collect = strtolower($tagname);
                    break;
                case 'LINK':
                    if (isset($attrs['REL'])) {
                        $rel = $attrs['REL'];
                    }
                    else {
                        $rel = '';
                    }
                    $this->parsed_data[$this->item_count]['link_'.$rel] = $attrs['HREF'];
                    break;
            }
        }
        if ($this->heading_block) {
            switch ($tagname) {
                case 'TITLE':
                case 'UPDATED':
                case 'LANGUAGE':
                case 'ID':
                    $this->collect = strtolower($tagname);
                    break;
                case 'LINK':
                    if (isset($attrs['REL'])) {
                        $rel = $attrs['REL'];
                    }
                    else {
                        $rel = '';
                    }
                    $this->parsed_data[0]['link_'.$rel] = $attrs['HREF'];
                    break;
            }
        }
        $this->depth++;
    }

    /**
     * ATOM end tag check
     * @param object $parser xml parser
     * @param string $tagname xml tag
     */
    function atom_end_element($parser, $tagname) {
        $this->collect = false;
        if ($tagname == 'ENTRY') {
            $this->data_block = false;
        }
        $this->depth--;
    }

    /**
     * Collect atom character data
     * @param object $parser xml parser
     * @param string $data xml data
     */
    function atom_character_data($parser, $data) {
        if ($this->heading_block && $this->collect) {
            $this->parsed_data[0][$this->collect] = trim($data);
        }
        if ($this->data_block && $this->collect) {
            if ($this->collect == 'updated' || $this->collect == 'modified') {
                $this->collect = 'pubdate';
            }
            if (isset($this->parsed_data[$this->item_count][$this->collect])) {
                $this->parsed_data[$this->item_count][$this->collect] .= trim($data);
            }
            else {
                $this->parsed_data[$this->item_count][$this->collect] = trim($data);
            }
        }
    }
    /**
     * Parse an RSS feed element
     * @param object $parser xml parser
     * @param string $tagname xml tag name
     * @param array $attrs tag attributes
     */
    function rss_start_element($parser, $tagname, $attrs) {
        if ($tagname == 'FEED') {
            $this->heading_block = true;
        }
        if ($tagname == 'ITEM') {
            $this->heading_block = false;
            $this->item_count++;
            $this->data_block = true;
        }
        if ($this->data_block) {
            switch ($tagname) {
                case 'TITLE':
                case 'LINK':
                case 'DESCRIPTION':
                case 'GUID':
                case 'PUBDATE':
                case 'DC:DATE':
                case 'DC:CREATOR':
                case 'AUTHOR':
                    $this->collect = strtolower($tagname);
                    break;
            }
        }
        if ($this->heading_block) {
            switch ($tagname) {
                case 'TITLE':
                case 'PUBDATE':
                case 'LANGUAGE':
                case 'DESCRIPTION':
                case 'LINK':
                    $this->collect = strtolower($tagname);
                    break;
                    
            }
        }
        $this->depth++;
    }

    /**
     * RSS end tag check
     * @param object $parser xml parser
     * @param string $tagname xml tag
     */
    function rss_end_element($parser, $tagname) {
        $this->collect = false;
        if ($tagname == 'ITEM') {
            $this->data_block = false;
        }
        $this->depth--;
    }

    /**
     * Collect RSS character data
     * @param object $parser xml parser
     * @param string $data xml data
     */
    function rss_character_data($parser, $data) {
        if ($this->heading_block && $this->collect) {
            $this->parsed_data[0][$this->collect] = $data;
        }
        if ($this->data_block && $this->collect) {
            if (isset($this->parsed_data[$this->item_count][$this->collect])) {
                $this->parsed_data[$this->item_count][$this->collect] .= $data;
            }
            else {
                $this->parsed_data[$this->item_count][$this->collect] = $data;
            }

        }
    }
}


