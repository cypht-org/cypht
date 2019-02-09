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
    private $headers = array('X-Mailer' => 'Cypht', 'MIME-Version' => '1.0');
    private $boundary = '';
    private $attachments = array();
    private $body = '';
    private $text_body = '';
    private $html = false;
    private $final_msg = '';

    /* build mime message data */
    function __construct($to, $subject, $body, $from, $html=false, $cc='', $bcc='', $in_reply_to_id='', $from_name='', $reply_to='') {
        if ($cc) {
            $this->headers['Cc'] = $cc;
        }
        if ($in_reply_to_id) {
            $this->headers['In-Reply-To'] = $in_reply_to_id;
        }
        $this->bcc = $bcc;
        if ($from_name) {
            $this->headers['From'] = sprintf('"%s" <%s>', $from_name, $from);
        }
        else {
            $this->headers['From'] = $from;
        }
        if ($reply_to) {
            $this->headers['Reply-To'] = $reply_to;
        }
        else {
            $this->headers['Reply-To'] = $from;
        }
        $this->headers['To'] = $to;
        $this->headers['Subject'] = html_entity_decode($subject, ENT_QUOTES);
        $this->headers['Date'] = date('r');
        $this->headers['Message-Id'] = '<'.md5(uniqid(rand(),1)).'@'.php_uname('n').'>';
        $this->boundary = str_replace(array('=', '/', '+'), '', Hm_Crypt::unique_id(48));
        $this->html = $html;
        $this->body = $body;
    }

    /* add attachments */
    function add_attachments($files) {
        $this->attachments = $files;
    }

    function process_attachments() {
        $res = '';
        $closing = false;
        foreach ($this->attachments as $file) {
            $content = Hm_Crypt::plaintext(@file_get_contents($file['filename']), Hm_Request_Key::generate());
            if ($content) {
                $closing = true;
                if (array_key_exists('no_encoding', $file) || (array_key_exists('type', $file) && $file['type'] == 'message/rfc822')) {
                    $res .= sprintf("\r\n--%s\r\nContent-Type: %s; name=\"%s\"\r\nContent-Description: %s\r\n".
                        "Content-Disposition: attachment; filename=\"%s\"\r\nContent-Transfer-Encoding: 7bit\r\n\r\n%s",
                        $this->boundary, $file['type'], $file['name'], $file['name'], $file['name'], $content);
                }
                else {
                    $content = chunk_split(base64_encode($content));
                    $res .= sprintf("\r\n--%s\r\nContent-Type: %s; name=\"%s\"\r\nContent-Description: %s\r\n".
                        "Content-Disposition: attachment; filename=\"%s\"\r\nContent-Transfer-Encoding: base64\r\n\r\n%s",
                        $this->boundary, $file['type'], $file['name'], $file['name'], $file['name'], $content);
                }

            }
        }
        if ($closing) {
            $res = rtrim($res, "\r\n").sprintf("\r\n--%s--\r\n", $this->boundary);
        }
        return $res;
    }

    /* output mime message */
    function get_mime_msg() {
        $this->prep_message_body();
        $res = '';
        $headers = '';
        foreach ($this->headers as $name => $val) {
            if (!trim($val)) {
                continue;
            }
            $headers .= sprintf("%s: %s\r\n", $name, rtrim($this->prep_fld($val, $name)));
        }
        if (!$this->final_msg) {
            if ($this->html) {
                $res .= $this->text_body;
            }
            $res .="\r\n".$this->body;
            if (!empty($this->attachments)) {
                $res .= $this->process_attachments();
            }
        }
        else {
            $res = $this->final_msg;
        }
        $this->final_msg = $res;
        return $headers.$res;
    }

    function set_auto_bcc($addr) {
        $this->headers['Bcc'] = $addr;
        $this->headers['X-Auto-Bcc'] = 'cypht';
    }

    function quote_fld($val) {
        if (!trim($val)) {
            return '';
        }
        if (!preg_match("/^[a-zA-Z0-9 !#$%&'\*\+\-\/\=\?\^_`\{\|\}]+$/", $val)) {
            return sprintf('"%s"', $val);
        }
        return $val;
    }

    function split_val($val, $bsize) {
        $count = ceil($bsize/75);
        $size = round(strlen($val)/$count);
        return str_split($val, $size);
    }

    function encode_fld($val, $single=false) {
        if ($single) {
            $parts = array($val);
        }
        else {
            $parts = explode(' ', $val);
        }
        $res = array();
        $prior = false;
        foreach ($parts as $v) {
            if (preg_match('/(?:[^\x00-\x7F])/',$v) === 1) {
                $bsize = round(((strlen($v)*4)/3)+13);
                if ($bsize > 75) {
                    foreach ($this->split_val($v, $bsize) as $slice) {
                        $res[] = $this->encode_fld($slice);
                    }
                }
                else {
                    if ($prior && !$single) {
                        $res[] = '=?UTF-8?B?'.base64_encode(' '.$v).'?=';
                    }
                    else {
                        $res[] = '=?UTF-8?B?'.base64_encode($v).'?=';
                    }
                    $prior = true;
                }
            }
            else {
                $prior = false;
                $res[] = $v;
            }
        }
        return implode(' ', $res);
    }

    function prep_fld($val, $name) {
        if (in_array($name, array('To', 'From', 'Cc', 'Reply-to'), true)) {
            $res = array();
            foreach(process_address_fld($val) as $vals) {
                $display_name = $this->encode_fld($vals['label'], true);
                $display_name = $this->quote_fld($display_name);
                if ($display_name) {
                    $res[] = sprintf('%s <%s>', $display_name, $vals['email']);
                }
                else {
                    $res[] = sprintf('<%s>', $vals['email']);
                }
            }
            return implode(', ', $res);
        }
        return $this->encode_fld($val);
    }

    function find_addresses($str) {
        $res = array();
        foreach (process_address_fld($str) as $vals) {
            $res[] = $vals['email'];
        }
        return $res;
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
            $res = array_merge($res, $this->find_addresses($v));
        }
        return $res;
    }

    function format_message_text($body) {
        $message = trim($body);
        $message = str_replace("\r\n", "\n", $message);
        $lines = explode("\n", $message);
        $new_lines = array();
        foreach($lines as $line) {
            $new_lines[] = trim($line, "\r\n")."\r\n";
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
        return str_replace('.', '=2E', quoted_printable_encode($string));
    }
}

