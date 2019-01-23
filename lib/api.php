<?php

/**
 * Easily talk to APIs using Curl
 * @package framework
 * @subpackage api
 */

/**
 * Class for sending commands to APIs
 */
class Hm_API_Curl {

    public $last_status;
    public $format = '';

    /**
     * Init
     * @param string $format format of the result
     */
    public function __construct($format='json') {
        $this->format = $format;
    }

    /**
     * Execute command
     * @param string $url url to fetch content from
     * @param array $headers HTTP header array
     * @param array $post post fields
     * @return array
     */
    public function command($url, $headers=array(), $post=array(), $body='', $method=false) {
        $ch = Hm_Functions::c_init();
        $this->curl_setopt($ch, $url, $headers);
        $this->curl_setopt_post($ch, $post);
        if ($method) {
            Hm_Functions::c_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        if ($body) {
            Hm_Functions::c_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        return $this->curl_result($ch);
    }

    /**
     * Setup curl options
     * @param resource|false $ch curl connection
     * @param string $url url to fetch content from
     * @param array $headers HTTP headers
     * @return void
     */
    private function curl_setopt($ch, $url, $headers) {
        Hm_Functions::c_setopt($ch, CURLOPT_URL, $url);
        Hm_Functions::c_setopt($ch, CURLOPT_USERAGENT, 'hm3');
        Hm_Functions::c_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        Hm_Functions::c_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        Hm_Functions::c_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($this->format == 'binary') {
            Hm_Functions::c_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        }
    }

    /**
     * Setup optional post properties
     * @param resource|false $ch curl connection
     * @param array $post post fields
     * @return void
     */
    private function curl_setopt_post($ch, $post) {
        if (!empty($post)) {
            Hm_Functions::c_setopt($ch, CURLOPT_POST, true);
            Hm_Functions::c_setopt($ch, CURLOPT_POSTFIELDS, $this->format_post_data($post));
        }
    }

    /**
     * Process a curl request result
     * @param resource $ch curl connection
     * @return array
     */
    private function curl_result($ch) {
        $curl_result = Hm_Functions::c_exec($ch);
        $this->last_status = Hm_Functions::c_status($ch);
        if ($this->format != 'json') {
            return $curl_result;
        }
        $result = @json_decode($curl_result, true);
        if ($result === NULL) {
            return array();
        }
        return $result;
    }

    /**
     * Format key value pairs into post field format
     * @param array $data fields to format
     * @return string
     */
    private function format_post_data($data) {
        $post = array();
        if (!is_array($data)) {
            return $data;
        }
        foreach ($data as $name => $value) {
            $post[] = urlencode($name).'='.urlencode($value);
        }
        return implode('&', $post);
    }
}

