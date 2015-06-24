<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Format a message body that has HMTL markup
 * @subpackage core/functions
 * @param string $str message HTML
 * @param bool $external_resources flag to allow external resources in the HTML
 * @return string
 */
function format_msg_html($str, $external_resources=false) {
    require APP_PATH.'third_party/HTMLPurifier.standalone.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    if (!$external_resources) {
        $config->set('URI.AllowedSchemes', array('data' => true));
    }
    $config->set('HTML.TargetBlank', true);
    $config->set('Filter.ExtractStyleBlocks.TidyImpl', true);
    $purifier = new HTMLPurifier($config);
    $res = @$purifier->purify($str);
    return $res;
}

/**
 * Convert HTML to plain text
 * @param string $html content to convert
 * @return string
 */
function convert_html_to_text($html) {
    require APP_PATH.'third_party/Html2Text.php';
    $html = new \Html2Text\Html2Text($html, array('do_links' => 'table', 'width' => 0));
    return $html->getText();
}

/**
 * Format image data
 * @subpackage core/functions
 * @param string $str binary image data
 * @param string $mime_type type of image
 * return string
 */
function format_msg_image($str, $mime_type) {
    return '<img alt="" src="data:image/'.$mime_type.';base64,'.chunk_split(base64_encode($str)).'" />';
}

/**
 * Format a plain text message
 * @subpackage core/functions
 * @param string $str message text
 * @param object $output_mod Hm_Output_Module
 */
function format_msg_text($str, $output_mod, $links=true) {
    $str = str_replace("\t", '    ', $str);
    $str = nl2br(str_replace(' ', '<wbr>', ($output_mod->html_safe($str))));
    if ($links) {
        $link_regex = "/((http|ftp|rtsp)s?:\/\/(%[[:digit:]A-Fa-f][[:digit:]A-Fa-f]|[-_\.!~\*';\/\?#:@&=\+$,\[\]%[:alnum:]])+)/m";
        $str = preg_replace($link_regex, "<a target=\"_blank\" href=\"$1\">$1</a>", $str);
    }
    return str_replace('<wbr>', '&#160;<wbr>', $str);
}

/**
 * Format reply text
 * @subpackage core/functions
 * @param string $txt message text
 * @return string
 */
function format_reply_text($txt) {
    $lines = explode("\n", $txt);
    $new_lines = array();
    foreach ($lines as $line) {
        $pre = '> ';
        if (preg_match("/^(>\s*)+/", $line, $matches)) {
            $pre .= $matches[1];
        }
        $wrap = 75 + strlen($pre);
        $new_lines[] = preg_replace("/$pre /", "$pre", "> ".wordwrap($line, $wrap, "\n$pre"));
    }
    return implode("\n", $new_lines);
}

/**
 * Get reply to address
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $type type (forward, reply, reply_all)
 * @return string
 */
function reply_to_address($headers, $type) {
    $msg_to = '';
    if ($type == 'forward') {
        return $msg_to;
    }
    if (array_key_exists('Reply-to', $headers)) {
        $msg_to = $headers['Reply-to'];
    }
    elseif (array_key_exists('From', $headers)) {
        $msg_to = $headers['From'];
    }
    elseif (array_key_exists('Sender', $headers)) {
        $msg_to = $headers['Sender'];
    }
    elseif (array_key_exists('Return-path', $headers)) {
        $msg_to = $headers['Return-path'];
    }
    return $msg_to;
}

/**
 * Get reply to subject
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $type type (forward, reply, reply_all)
 * @return string
 */
function reply_to_subject($headers, $type) {
    $subject = '';
    if (array_key_exists('Subject', $headers)) {
        if ($type == 'reply') {
            if (!preg_match("/^re:/i", trim($headers['Subject']))) {
                $subject = sprintf("Re: %s", $headers['Subject']);
            }
        }
        elseif ($type == 'forward') {
            if (!preg_match("/^fwd:/i", trim($headers['Subject']))) {
                $subject = sprintf("Fwd: %s", $headers['Subject']);
            }
        }
        if (!$subject) {
            $subject = $headers['Subject'];
        }
    }
    return $subject;
}

/**
 * Get reply message lead in
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $type type (forward, reply, reply_all)
 * @param string $to reply to value
 * @param object $output_mod output module object
 * @return string
 */
function reply_lead_in($headers, $type, $to, $output_mod) {
    $lead_in = '';
    if ($type == 'reply') {
        if (array_key_exists('Date', $headers)) {
            if ($to) {
                $lead_in = sprintf($output_mod->trans('On %s %s said')."\n\n", $headers['Date'], $to);
            }
            else {
                $lead_in = sprintf($output_mod->trans('On %s, somebody said')."\n\n", $headers['Date']);
            }
        }
    }
    elseif ($type == 'forward') {
        $flds = array();
        foreach( array('From', 'Date', 'Subject') as $fld) {
            if (array_key_exists($fld, $headers)) {
                $flds[$fld] = $headers[$fld];
            }
        }
        $lead_in = "\n\n----- ".$output_mod->trans('begin forwarded message')." -----\n\n";
        foreach ($flds as $fld => $val) {
            $lead_in .= $fld.': '.$val."\n";
        }
        $lead_in .= "\n";
    }
    return $lead_in;
}

/**
 * Format reply field details
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $body message body
 * @param string $lead_in body lead in text
 * @param string $reply_type type (forward, reply, reply_all)
 * @param array $struct message structure details
 * @param int $html set to 1 if the output should be HTML
 * @return array
 */
function reply_format_body($headers, $body, $lead_in, $reply_type, $struct, $html) {
    $msg = '';
    $type = 'textplain';
    if (array_key_exists('type', $struct) && array_key_exists('subtype', $struct)) {
        $type = strtolower($struct['type']).strtolower($struct['subtype']);
    }
    if ($html) {
        $msg = format_reply_as_html($body, $type, $reply_type, $lead_in);
    }
    else {
        $msg = format_reply_as_text($body, $type, $reply_type, $lead_in);
    }
    return $msg;
}

/**
 * Format reply text as HTML
 * @subpackage core/functions
 * @param string $body message body
 * @param string $type MIME type
 * @param string $reply_type type (forward, reply, reply_all)
 * @param string $lead_in body lead in text
 * @return string
 */
function format_reply_as_html($body, $type, $reply_type, $lead_in) {
    if ($type == 'textplain') {
        if ($reply_type == 'reply') {
            $msg = nl2br($lead_in.format_reply_text($body));
        }
        elseif ($reply_type == 'forward') {
            $msg = nl2br($lead_in.$body);
        }
    }
    elseif ($type == 'texthtml') {
        $msg = nl2br($lead_in).'<hr /><blockquote>'.format_msg_html($body).'</blockquote>';
    }
    return $msg;
}

/**
 * Format reply text as text
 * @subpackage core/functions
 * @param string $body message body
 * @param string $type MIME type
 * @param string $reply_type type (forward, reply, reply_all)
 * @param string $lead_in body lead in text
 * @return string
 */
function format_reply_as_text($body, $type, $reply_type, $lead_in) {
    if ($type == 'texthtml') {
        if ($reply_type == 'reply') {
            $msg = $lead_in.format_reply_text(convert_html_to_text($body));
        }
        elseif ($reply_type == 'forward') {
            $msg = $lead_in.convert_html_to_text($body);
        }
    }
    elseif ($type == 'textplain') {
        if ($reply_type == 'reply') {
            $msg = $lead_in.format_reply_text($body);
        }
        else {
            $msg = $lead_in.$body;
        }
    }
    return $msg;
}

/**
 * Get reply field details
 * @subpackage core/functions
 * @param string $body message body
 * @param array $headers message headers
 * @param array $struct message structure details
 * @param int $html set to 1 if the output should be HTML
 * @param string $type optional type (forward, reply, reply_all)
 * @param object $output_mod output module object
 * @return array
 */
function format_reply_fields($body, $headers, $struct, $html, $output_mod, $type='reply') {
    $msg_to = '';
    $msg = '';
    $subject = reply_to_subject($headers, $type);
    $msg_to = reply_to_address($headers, $type);
    $lead_in = reply_lead_in($headers, $type, $msg_to, $output_mod);
    $msg = reply_format_body($headers, $body, $lead_in, $type, $struct, $html);
    return array($msg_to, $subject, $msg);
}

