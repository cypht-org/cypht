<?php

/**
 * SMTP libs
 * @package modules
 * @subpackage smtp
 */

/**
 * Build a MIME message
 * @subpackage smtp/lib
 */
class Hm_MIME_Msg {
    private $bcc = '';
    private $headers = array('MIME-Version' => '1.0');
    private $boundary = '';
    private $attachments = array();
    private $body = '';
    private $text_body = '';
    private $html = false;
    private $allow_unqualified_addresses = false;

    /* build mime message data */
    function __construct($to, $subject, $body, $from, $html=false, $cc='', $bcc='', $reply_to_id='') {
        if ($cc) {
            $this->headers['Cc'] = $cc;
        }
        if ($reply_to_id) {
            $this->headers['In-Reply-To'] = $reply_to_id;
        }
        $this->bcc = $bcc;
        $this->headers['From'] = $from;
        $this->headers['Reply-To'] = $from;
        $this->headers['To'] = $this->encode_header_fld($to);
        $this->headers['Subject'] = $this->encode_header_fld($subject);
        $this->headers['Date'] = date('r');
        $this->headers['Message-Id'] = '<'.md5(uniqid(rand(),1)).'@'.php_uname('n').'>';
        $this->boundary = Hm_Crypt::unique_id(32);
        $this->html = $html;
        $this->body = $body;
    }

    /* add attachments */
    function add_attachments($files) {
        $this->attachments = $files;
    }

    function process_attachments() {
        $res = '';
        foreach ($this->attachments as $file) {
            $content = Hm_Crypt::plaintext(@file_get_contents($file['filename']), Hm_Request_Key::generate());
            if ($content) {
                $content = chunk_split(base64_encode($content));
                $res .= sprintf("\r\n--%s\r\nContent-Type: %s; name=\"%s\"\r\nContent-Description: %s\r\n".
                    "Content-Disposition: attachment; filename=\"%s\"\r\nContent-Transfer-Encoding: base64\r\n\r\n%s",
                    $this->boundary, $file['type'], $file['name'], $file['name'], $file['name'], $content);

            }
        }
        return $res;
    }

    /* output mime message */
    function get_mime_msg() {
        $this->prep_message_body();
        $res = '';
        foreach ($this->headers as $name => $val) {
            if (!trim($val)) {
                continue;
            }
            $res .= sprintf("%s: %s\r\n", $name, $val);
        }
        if ($this->html) {
            $res .= $this->text_body;
        }
        $res .="\r\n".$this->body;
        if (!empty($this->attachments)) {
            $res .= $this->process_attachments();
        }
        return $res;
    }

    function encode_header_fld($input, $email=true) {
        $res = array();
        $input = trim($input, ',; ');
        if (strstr($input, ' ')) {
            $parts = explode(' ', $input);
        }
        else {
            $parts[] = $input;
        }
        foreach ($parts as $v) {
            if (preg_match('/(?:[^\x00-\x7F])/',$v) === 1) {
                $leading_quote = false;
                $trailing_quote = false;
                if (substr($v, 0, 1) == '"') {
                    $v = substr($v, 1);
                    $leading_quote = true;
                }
                if (substr($v, -1) == '"') {
                    $trailing_quote = true;
                    $v = substr($v, 0, -1);
                }
                $enc_val = '=?UTF-8?B?'.base64_encode($v).'?=';
                if ($leading_quote) {
                    $enc_val = '"'.$enc_val;
                }
                if ($trailing_quote) {
                    $enc_val = $enc_val.'"';
                }
                $res[] = $enc_val;
            }
            else {
                if ($email && strpos($v, '@') !== false && is_email($v)) {
                    $res[] = '<'.$v.'>';
                }
                else {
                    $res[] = $v;
                }
            }
        }
        $string = preg_replace("/\s{2,}/", ' ', trim(implode(' ', $res)));
        return $string;
    }

    function get_recipient_addresses() {
        $res = array();
        foreach (array('To', 'Cc', 'Bcc') as $fld) {
            if ($fld == 'Bcc') {
                $v = $this->bcc;
            }
            elseif (array_key_exists($fld, $this->headers)) {
                $v = $this->headers[$fld];
            }
            else {
                continue;
            }
            $v = trim(preg_replace("/(\r|\n|\t)/m", ' ', $v));
            $v = preg_replace("/(\"[^\"\\\]*(?:\\\.[^\"\\\]*)*\")/", ' ', $v);
            $v = str_replace(array(',', ';'), array(' , ', ' ; '), $v); 
            $v = preg_replace("/\s+/", ' ', $v);
            if (!$v) {
                continue;
            }
            $bits = explode(' ', $v);
            foreach ($bits as $val) {
                $val = trim($val);
                if (!$val) {
                    continue;
                }
                if (strstr($val, '@')) {
                    $address = ltrim(rtrim($val ,'>'), '<');
                    if (is_email($address)) {
                        $res[] = $address;
                    }
                }
            }
            if ($this->allow_unqualified_addresses) {
                $bits = preg_split("/(;|,)/", $v);
                foreach ($bits as $val) {
                        $val = trim($val);
                    if (!strstr($val, ' ') && !strstr($val, '@') && strlen($val) > 2) {
                        $res[] = $val;
                    }
                }
            }
        }
        return $res;
    }

    function format_message_text($body) {
        $message = trim($body);
        $message = str_replace("\r\n", "\n", $message);
        $lines = explode("\n", $message);
        $new_lines = array();
        foreach($lines as $line) {
            $line = trim($line, "\r\n")."\r\n";
            $new_lines[] = preg_replace("/^\.\r\n/", "..\r\n", $line);
        }
        return $this->qp_encode(implode('', $new_lines));
    }

    function prep_message_body() {
        $body = $this->body;
        if (!$this->html) {
            $body = mb_convert_encoding(trim($body), "HTML-ENTITIES", "UTF-8");
            $body = mb_convert_encoding($body, "UTF-8", "HTML-ENTITIES");
            if (!empty($this->attachments)) {
                $this->headers['Content-Type'] = 'multipart/mixed; boundary='.$this->boundary;
                $body = sprintf("--%s\r\nContent-Type: text/plain; charset=UTF-8; format=flowed\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n%s",
                    $this->boundary, $this->format_message_text($body));
            }
            else {
                $this->headers['Content-Type'] = 'text/plain; charset=UTF-8; format=flowed';
                $this->headers['Content-Transfer-Encoding'] = 'quoted-printable';
                $body = $this->format_message_text($body);
            }
        }
        else {
            $txt = convert_html_to_text($body);

            if (!empty($this->attachments)) {
                $alt_boundary = Hm_Crypt::unique_id(32);
                $this->headers['Content-Type'] = 'multipart/mixed; boundary='.$this->boundary;
                $this->text_body = sprintf("--%s\r\nContent-Type: multipart/alternative; boundary=".
                    "\"%s\"\r\n\r\n--%s\r\nContent-Type: text/plain; charset=UTF-8; ".
                    "format=flowed\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n%s",
                    $this->boundary, $alt_boundary, $alt_boundary, $this->format_message_text($txt));

                $body = sprintf("--%s\r\nContent-Type: text/html; charset=UTF-8; format=flowed\r\n".
                    "Content-Transfer-Encoding: quoted-printable\r\n\r\n%s\r\n\r\n--%s--",
                    $alt_boundary, $this->format_message_text($body), $alt_boundary);
            }
            else {
                $this->headers['Content-Type'] = 'multipart/alternative; boundary='.$this->boundary;
                $this->text_body = sprintf("--%s\r\nContent-Type: text/plain; charset=UTF-8; ".
                    "format=flowed\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n%s",
                    $this->boundary, $this->format_message_text($txt));
                $body = sprintf("--%s\r\nContent-Type: text/html; charset=UTF-8; format=flowed\r\n".
                    "Content-Transfer-Encoding: quoted-printable\r\n\r\n%s",
                    $this->boundary, $this->format_message_text($body));
            }
        }
        $this->body = $body;
    }

    function qp_encode($string) {
        $string = str_replace("\r\n", "\n", $string);
        $lines = explode("\n", $string);
        $new_lines = array();
        foreach ($lines as $v) {
            $new_line = '';
            $char_count = 0;
            while ($v) {
                $char = substr($v, 0, 1);
                $ord = ord($char);
                $v = substr($v, 1);
                switch (true) {
                    case ($ord > 32 && $ord < 61) || ($ord > 61 && $ord < 127):
                        $new_line .= $char;
                        $char_count++;
                        break;
                    case $ord == 9:
                    case $ord == 32:
                        $new_line .= $char;
                        break;
                    default:
                        $new_line .= '='.strtoupper(dechex($ord));
                        $char_count += 3;
                        break;
                }
                if ($char_count > 72) {
                    $new_lines[] = $new_line.'=';
                    $char_count = 0;
                    $new_line = '';
                }
            }
            $new_lines[] = $new_line;
        }
        return implode("\r\n", $new_lines);
    }
}

