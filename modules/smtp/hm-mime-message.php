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
    function __construct($to, $subject, $body, $from, $html=false, $cc='', $bcc='', $in_reply_to_id='', $from_name='', $reply_to='', $delivery_receipt='', $schedule='', $profile_id = '') {
        if ($cc) {
            $this->headers['Cc'] = $cc;
        }
        if ($schedule) {
            $this->headers['X-Schedule'] = $schedule;
            $this->headers['X-Profile-ID'] = $profile_id;
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
        if ($delivery_receipt) {
            $this->headers['X-Delivery'] = $delivery_receipt;
        }
        $this->headers['To'] = $to;
        $this->headers['Subject'] = html_entity_decode($subject, ENT_QUOTES);
        $this->headers['Date'] = date('r');
        $this->headers['Message-Id'] = '<'.md5(uniqid(rand(),1)).'@'.php_uname('n').'>';
        $this->boundary = str_replace(array('=', '/', '+'), '', Hm_Crypt::unique_id(48));
        $this->html = $html;
        $this->body = $body;
    }

    /* return headers array */
    function get_headers() {
      return $this->headers;
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
        if (!empty($this->body)) {
            $this->prep_message_body();
        }
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
        $size = round(mb_strlen($val)/$count);
        return mb_str_split($val, $size);
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
                $bsize = round(((mb_strlen($v)*4)/3)+13);
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

    static function find_addresses($str) {
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
            $res = array_merge($res, self::find_addresses($v));
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

/**
 * Build a MIME message for Email Reactions
 * @subpackage smtp/lib
 */
class Hm_Reaction_MIME_Msg {
    private $headers = array('X-Mailer' => 'Cypht', 'MIME-Version' => '1.0');
    private $boundary = '';
    private $body = '';

    /* build reaction mime message data */
    function __construct($from_address, $from_name, $to_address, $subject, $reaction_content, $emoji_char, $original_message_data) {
        $this->boundary = str_replace(array('=', '/', '+'), '', Hm_Crypt::unique_id(48));

        $formatted_to = $this->format_address_field($to_address);
        $formatted_from = $from_address;
        if ($from_name) {
            $formatted_from = '"' . mb_encode_mimeheader(str_replace('"', '', $from_name), 'UTF-8', 'Q') . '" <' . $from_address . '>';
        }

        $fallback_text = 'Reacted with ' . $emoji_char;
        $fallback_html = '<p>' . htmlspecialchars($fallback_text, ENT_QUOTES, 'UTF-8') . '</p>';
        $encoded_reaction_json = $this->qp_encode($reaction_content);
        $encoded_fallback_text = $this->qp_encode($fallback_text);
        $encoded_fallback_html = $this->qp_encode($fallback_html);

        $encoded_subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');

        $in_reply_to_header = '';
        $references_header = '';
        if (!empty($original_message_data['message_id'])) {
            $in_reply_to_header = '<' . $original_message_data['message_id'] . '>';
            $references_header = $in_reply_to_header;
            if (!empty($original_message_data['references'])) {
                $ref_parts = preg_split('/\s+/', trim($original_message_data['references']));
                $ref_parts[] = $in_reply_to_header;
                $references_header = implode(' ', array_unique($ref_parts));
            }
        }

        $this->headers['Date'] = date('r');
        $this->headers['Message-Id'] = '<' . md5(uniqid(rand(), 1)) . '@' . php_uname('n') . '>';
        $this->headers['Subject'] = $encoded_subject;
        $this->headers['From'] = $formatted_from;
        $this->headers['To'] = $formatted_to;
        if ($in_reply_to_header) {
            $this->headers['In-Reply-To'] = $in_reply_to_header;
            $this->headers['References'] = $references_header;
        }
        $this->headers['Content-Type'] = 'multipart/alternative; boundary="' . $this->boundary . '"';

        $this->body = $this->build_mime_body_parts($encoded_fallback_text, $encoded_reaction_json, $encoded_fallback_html);
    }

    /* output mime message */
    function get_mime_msg() {
        $header_string = '';
        foreach ($this->headers as $name => $value) {
            if (!trim($value)) {
                continue;
            }
            $header_string .= sprintf("%s: %s\r\n", $name, $value);
        }
        return $header_string . "\r\n" . $this->body;
    }

    /* get recipient addresses */
    function get_recipient_addresses() {
        return Hm_MIME_Msg::find_addresses($this->headers['To']);
    }

    /* build multipart body */
    private function build_mime_body_parts($fallback_text, $reaction_json, $fallback_html) {
        $body = '--' . $this->boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=utf-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= $fallback_text . "\r\n\r\n";

        $body .= '--' . $this->boundary . "\r\n";
        $body .= "Content-Type: text/vnd.google.email-reaction+json; charset=utf-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= $reaction_json . "\r\n\r\n";

        $body .= '--' . $this->boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=utf-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= $fallback_html . "\r\n\r\n";

        $body .= '--' . $this->boundary . "--\r\n";
        return $body;
    }

    /* encode quoted-printable */
    private function qp_encode($string) {
        return str_replace('.', '=2E', quoted_printable_encode($string));
    }

    /* format email address field */
    private function format_address_field($address_string) {
        return $address_string;
    }
}