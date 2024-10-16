<?php

use ZBateson\MailMimeParser\Message;

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Build a source list for sent folders
 * @subpackage imap/functions
 * @param string $callback javascript callback function name
 * @param array $configured user specific sent folders
 * @param string $inbox include inbox in search for auto-bcc messages
 * @return array
 */
if (!hm_exists('imap_sources')) {
function imap_sources($callback, $mod, $folder = 'sent') {
    $inbox = $mod->user_config->get('smtp_auto_bcc_setting', DEFAULT_SMTP_AUTO_BCC);
    $sources = array();
    $folder = $folder == 'drafts' ? 'draft': $folder;
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        if (array_key_exists('hide', $vals) && $vals['hide']) {
            continue;
        }
        $folders = get_special_folders($mod, $index);
        if (array_key_exists($folder, $folders) && $folders[$folder]) {
            $sources[] = array('callback' => $callback, 'folder' => bin2hex($folders[$folder]), 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
        }
        elseif ($inbox) {
            $sources[] = array('callback' => $callback, 'folder' => bin2hex('INBOX'), 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
        }
        else {
            $sources[] = array('callback' => $callback, 'folder' => bin2hex('SPECIAL_USE_CHECK'), 'nodisplay' => true, 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
        }
    }
    return $sources;
}}

/**
 * Build a source list
 * @subpackage imap/functions
 * @param string $callback javascript callback function name
 * @param array $custom user specific assignments
 * @return array
 */
if (!hm_exists('imap_data_sources')) {
function imap_data_sources($callback, $custom=array()) {
    $sources = array();
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        if (array_key_exists('hide', $vals) && $vals['hide']) {
            continue;
        }
        if (!array_key_exists('user', $vals)) {
            continue;
        }
        $sources[] = array('callback' => $callback, 'folder' => bin2hex('INBOX'), 'type' => 'imap', 'name' => $vals['name'], 'id' => $index);
    }
    foreach ($custom as $path => $type) {
        $parts = explode('_', $path, 3);
        $remove_id = false;

        if ($type == 'add') {
            $details = Hm_IMAP_List::dump($parts[1]);
            if ($details) {
                $sources[] = array('callback' => $callback, 'folder' => $parts[2], 'type' => 'imap', 'name' => $details['name'], 'id' => $parts[1]);
            }
        }
        elseif ($type == 'remove') {
            foreach ($sources as $index => $vals) {
                if ($vals['folder'] == $parts[2] && $vals['id'] == $parts[1]) {
                    $remove_id = $index;
                    break;
                }
            }
            if ($remove_id !== false) {
                unset($sources[$remove_id]);
            }
        }
    }
    return $sources;
}}

/**
 * Prepare and format message list data
 * @subpackage imap/functions
 * @param array $msgs list of message headers to format
 * @param object $mod Hm_Output_Module
 * @return void
 */
if (!hm_exists('prepare_imap_message_list')) {
function prepare_imap_message_list($msgs, $mod, $type) {
    $style = $mod->get('news_list_style') ? 'news' : 'email';
    if ($mod->get('is_mobile')) {
        $style = 'news';
    }
    $res = format_imap_message_list($msgs, $mod, $type, $style);
    $mod->out('formatted_message_list', $res);
}}

/**
 * Build HTML for a list of IMAP folders
 * @subpackage imap/functions
 * @param array $folders list of folder data
 * @param mixed $id IMAP server id
 * @param object $mod Hm_Output_Module
 * @return string
 */
if (!hm_exists('format_imap_folder_section')) {
function format_imap_folder_section($folders, $id, $output_mod, $with_input = false, $can_share_folders = false) {
    $results = '<ul class="inner_list">';
    $manage = $output_mod->get('imap_folder_manage_link');

    foreach ($folders as $folder_name => $folder) {
        $folder_name = bin2hex($folder_name);
        $results .= '<li class="imap_'.$id.'_'.$output_mod->html_safe($folder_name).'">';
        if ($folder['children']) {
            $results .= '<a href="#" class="imap_folder_link expand_link d-inline-flex" data-target="imap_'.$id.'_'.$output_mod->html_safe($folder_name).'"><i class="bi bi-plus-circle-fill"></i></a>';
        }
        else {
            $results .= '<i class="bi bi-folder2-open"></i> ';
        }
        if (!$folder['noselect']) {
            if (!$folder['clickable']) {
                $attrs = 'tabindex="0"';
                if (!$with_input && isset($folder['subscribed']) && !$folder['subscribed']) {
                    $attrs .= ' class="folder-disabled"';
                }
            } else {
                $attrs = 'id="main-link" data-id="imap_'.$id.'_'.$output_mod->html_safe($folder_name).
                '" href="?page=message_list&amp;list_path='.
                urlencode('imap_'.$id.'_'.$output_mod->html_safe($folder_name)).'"';
            }
            if (mb_strlen($output_mod->html_safe($folder['basename']))>15) {
                $results .= '<a ' . $attrs .
                    ' title="'.$output_mod->html_safe($folder['basename']).
                    '">'.mb_substr($output_mod->html_safe($folder['basename']),0,15).'...</a>';
            }
            else {
                $results .= '<a ' . $attrs. '>'.$output_mod->html_safe($folder['basename']).'</a>';
            }
        }
        else {
            $results .= $output_mod->html_safe($folder['basename']);
        }
        if ($with_input) {
            $results .= '<input type="checkbox" value="1" class="folder_subscription" id="'.$output_mod->html_safe($folder_name).'" name="'.$folder_name.'" '.($folder['subscribed']? 'checked="checked"': '').($folder['special']? ' disabled="disabled"': '').' />';
        }
        $results .= '<span class="unread_count unread_imap_'.$id.'_'.$output_mod->html_safe($folder_name).'"></span>';
        if($can_share_folders) {
            $results .= '<div class="dropdown"><a href="#" class="action-link" data-bs-toggle="dropdown" aria-expanded="false"><i class="icon bi bi-three-dots-vertical"></i></a><ul class="dropdown-menu dropdown-menu"><li data-id="'.$id.'" data-folder-uid="'.$output_mod->html_safe($folder_name).'" data-folder="'.$output_mod->html_safe($folder['basename']).'"><a href="#" class="dropdown-item share"><i class="icon bi bi-share"></i> Share</a></ul></div>';
        }
        $results .= '</li>';
    }
    if ($manage) {
        $results .= '<li class="manage_folders_li"><i class="bi bi-gear-wide me-1"></i><a class="manage_folder_link" href="'.$manage.'">'.$output_mod->trans('Manage Folders').'</a></li>';
    }
    $f = $output_mod->get('folder', '');
    $quota = $output_mod->get('quota');
    $quota_max = $output_mod->get('quota_max');
    if (!$f && $quota) {
        $results .= '<li class="quota_info"><div class="progress bg-secondary border"><div class="progress-bar bg-light" style="width:'.$quota.'%"></div></div>'.$quota.'% used on '.$quota_max.' MB</li>';
    }

    $results .= '</ul>';
    return $results;
}}

/**
 * Format a from/to field for message list display
 * @subpackage imap/functions
 * @param string $fld field to format
 * @return string
 */
if (!hm_exists('format_imap_from_fld')) {
function format_imap_from_fld($fld) {
    $res = array();
    foreach (process_address_fld($fld) as $vals) {
        if (trim($vals['label'])) {
            $res[] = $vals['label'];
        }
        elseif (trim($vals['email'])) {
            $res[] = $vals['email'];
        }
    }
    return implode(', ', $res);
}}

/**
 * Format a list of message headers
 * @subpackage imap/functions
 * @param array $msg_list list of message headers
 * @param object $mod Hm_Output_Module
 * @param mixed $parent_list parent list id
 * @param string $style list style (email or news)
 * @return array
 */
if (!hm_exists('format_imap_message_list')) {
function format_imap_message_list($msg_list, $output_module, $parent_list=false, $style='email') {
    $res = array();
    if ($msg_list === array(false)) {
        return $msg_list;
    }
    $show_icons = $output_module->get('msg_list_icons');
    $list_page = $output_module->get('list_page', 0);
    $list_sort = $output_module->get('list_sort', $output_module->get('default_sort_order'));
    $list_filter = $output_module->get('list_filter');
    foreach($msg_list as $msg) {
        $row_class = 'email';
        $icon = 'env_open';
        if (!$parent_list) {
            $parent_value = sprintf('imap_%s_%s', $msg['server_id'], $msg['folder']);
        }
        else {
            $parent_value = $parent_list;
        }
        $id = sprintf("imap_%s_%s_%s", $msg['server_id'], $msg['uid'], $msg['folder']);
        if (!trim($msg['subject'])) {
            $msg['subject'] = '[No Subject]';
        }
        $subject = $msg['subject'];
        if ($parent_list == 'sent') {
            $icon = 'sent';
            $from = $msg['to'];
        }
        else {
            $from = $msg['from'];
        }
        $from = format_imap_from_fld($from);
        $nofrom = '';
        if (!trim($from)) {
            $from = '[No From]';
            $nofrom = ' nofrom';
        }
        $is_snoozed = !empty($msg['x_snoozed']) && hex2bin($msg['folder']) == 'Snoozed';
        if ($is_snoozed) {
            $snooze_header = parse_snooze_header('X-Snoozed: '.$msg['x_snoozed']);
            $date = $snooze_header['until'];
            $timestamp = strtotime($date);
        } else {
            if ($list_sort == 'date') {
                $date_field = 'date';
            } else {
                $date_field = 'internal_date';
            }
            $date = translate_time_str(human_readable_interval($msg[$date_field]), $output_module);
            $timestamp = strtotime($msg[$date_field]);
        }

        $flags = array();
        if (!mb_stristr($msg['flags'], 'seen')) {
            $flags[] = 'unseen';
            if ($icon != 'sent') {
                $icon = 'env_closed';
            }
        }
        else {
            $row_class .= ' seen';
        }
        if (trim($msg['x_auto_bcc']) === 'cypht') {
            $from = preg_replace("/(\<.+\>)/U", '', $msg['to']);
            $icon = 'sent';
        }
        foreach (array('attachment', 'deleted', 'flagged', 'answered', 'draft') as $flag) {
            if (mb_stristr($msg['flags'], $flag)) {
                $flags[] = $flag;
            }
        }
        $source = $msg['server_name'];
        $row_class .= ' '.str_replace(' ', '_', $source);
        $row_class .= ' '.implode(' ', $flags);
        if ($msg['folder'] && hex2bin($msg['folder']) != 'INBOX') {
            $source .= '-'.preg_replace("/^INBOX.{1}/", '', hex2bin($msg['folder']));
        }
        $url = '?page=message&uid='.$msg['uid'].'&list_path='.sprintf('imap_%s_%s', $msg['server_id'], $msg['folder']).'&list_parent='.$parent_value;
        if ($list_page) {
            $url .= '&list_page='.$output_module->html_safe($list_page);
        }
        if ($list_sort) {
            $url .= '&sort='.$output_module->html_safe($list_sort);
        }
        if ($list_filter) {
            $url .= '&filter='.$output_module->html_safe($list_filter);
        }
        if (!$show_icons) {
            $icon = false;
        }

        //if (in_array('draft', $flags)) {
        //    $url = '?page=compose&list_path='.sprintf('imap_%s_%s', $msg['server_id'], $msg['folder']).'&uid='.$msg['uid'].'&imap_draft=1';
        //}

        if ($style == 'news') {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('icon_callback', $flags),
                    array('subject_callback', $subject, $url, $flags, $icon),
                    array('safe_output_callback', 'source', $source),
                    array('safe_output_callback', 'from'.$nofrom, $from, null, str_replace(array($from, '<', '>'), '', $msg['from'])),
                    array('date_callback', $date, $timestamp),
                ),
                $id,
                $style,
                $output_module,
                $row_class
            );
        }
        else {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('safe_output_callback', 'source', $source, $icon),
                    array('safe_output_callback', 'from'.$nofrom, $from, null, str_replace(array($from, '<', '>'), '', $msg['from'])),
                    array('subject_callback', $subject, $url, $flags),
                    array('date_callback', $date, $timestamp, $is_snoozed),
                    array('icon_callback', $flags)
                ),
                $id,
                $style,
                $output_module,
                $row_class
            );
        }
    }
    return $res;
}}

/**
 * Process message ids
 * @subpackage imap/functions
 * @param array $ids list of ids
 * @return array
 */
if (!hm_exists('process_imap_message_ids')) {
function process_imap_message_ids($ids) {
    $res = array();
    foreach (explode(',', $ids) as $id) {
        if (preg_match("/imap_(\S+)_(\S+)_(\S+)$/", $id, $matches)) {
            $server = $matches[1];
            $uid = $matches[2];
            $folder = $matches[3];
            if (!isset($res[$server])) {
                $res[$server] = array();
            }
            if (!isset($res[$server][$folder])) {
                $res[$server][$folder] = array();
            }
            $res[$server][$folder][] = $uid;
        }
    }
    return $res;
}}

/**
 * Format a message part row
 * @subpackage imap/functions
 * @param string $id message identifier
 * @param array $vals details of the message
 * @param object $mod Hm_Output_Module
 * @param int $level indention level
 * @param string $part currently selected part
 * @param string $dl_args base arguments for a download link URL
 * @param bool $use_icons flag to enable/disable message part icons
 * @param bool $simmple_view flag to hide complex message structure
 * @param bool $mobile flag to indicate a mobile browser
 * @return string
 */
if (!hm_exists('format_msg_part_row')) {
function format_msg_part_row($id, $vals, $output_mod, $level, $part, $dl_args, $at_args, $use_icons=false, $simple_view=false, $mobile=false) {
    $allowed = array(
        'textplain',
        'texthtml',
        'messagedisposition-notification',
        'messagedelivery-status',
        'messagerfc822-headers',
        'textcsv',
        'textcss',
        'textunknown',
        'textx-vcard',
        'textcalendar',
        'textx-vcalendar',
        'textx-sql',
        'textx-comma-separated-values',
        'textenriched',
        'textrfc822-headers',
        'textx-diff',
        'textx-patch',
        'applicationpgp-signature',
        'applicationx-httpd-php',
        'imagepng',
        'imagesvg+xml',
        'imagejpg',
        'imagejpeg',
        'imagepjpeg',
        'imagegif',
    );
    $icons = array(
        'text' => 'doc',
        'image' => 'camera',
        'application' => 'save',
        'multipart' => 'folder',
        'audio' => 'audio',
        'video' => 'monitor',
        'binary' => 'save',

        'textx-vcard' => 'calendar',
        'textcalendar' => 'calendar',
        'textx-vcalendar' => 'calendar',
        'applicationics' => 'calendar',
        'multipartdigest' => 'spreadsheet',
        'applicationpgp-keys' => 'key',
        'applicationpgp-signature' => 'key',
        'multipartsigned' => 'lock',
        'messagerfc822' => 'env_open',
        'octetstream' => 'paperclip',
    );
    $hidden_parts= array(
        'multipartdigest',
        'multipartsigned',
        'multipartmixed',
        'messagerfc822',
    );
    $lc_type = mb_strtolower($vals['type']).mb_strtolower($vals['subtype']);
    if ($simple_view) {
        if (filter_message_part($vals)) {
            return '';
        }
        if (in_array($lc_type, $hidden_parts, true)) {
            return '';
        }
    }
    if ($level > 6) {
        $class = 'row_indent_max';
    }
    else {
        $class = 'row_indent_'.$level;
    }
    $desc = get_part_desc($vals, $id, $part);
    $size = get_imap_size($vals);
    $res = '<tr';
    if ($id == $part) {
        $res .= ' class="selected_part"';
    }
    $res .= '><td><div class="'.$class.'">';
    $icon = false;
    if ($use_icons && array_key_exists($lc_type, $icons)) {
        $icon = $icons[$lc_type];
    }
    elseif ($use_icons && array_key_exists(mb_strtolower($vals['type']), $icons)) {
        $icon = $icons[mb_strtolower($vals['type'])];
    }
    if ($icon) {
        $res .= '<i class="bi bi-file-plus-fill msg_part_icon"></i> ';
    }
    else {
        $res .= '<i class="bi bi-file-plus-fill msg_part_icon msg_part_placeholder"></i> ';
    }
    if (in_array($lc_type, $allowed, true)) {
        $res .= '<a href="#" class="msg_part_link" data-message-part="'.$output_mod->html_safe($id).'">'.$output_mod->html_safe(mb_strtolower($vals['type'])).
            ' / '.$output_mod->html_safe(mb_strtolower($vals['subtype'])).'</a>';
    }
    else {
        $res .= $output_mod->html_safe(mb_strtolower($vals['type'])).' / '.$output_mod->html_safe(mb_strtolower($vals['subtype']));
    }
    if ($mobile) {
        $res .= '<div class="part_size">'.$output_mod->html_safe($size);
        $res .= '</div><div class="part_desc">'.$output_mod->html_safe(decode_fld($desc)).'</div>';
        $res .= '<div class="download_link"><a href="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></div></td>';
    }
    else {
        $res .= '</td><td class="part_size">'.$output_mod->html_safe($size);
        if (!$simple_view) {
            $res .= '</td><td class="part_encoding">'.(isset($vals['encoding']) ? $output_mod->html_safe(mb_strtolower($vals['encoding'])) : '').
                '</td><td class="part_charset">'.(isset($vals['attributes']['charset']) && trim($vals['attributes']['charset']) ? $output_mod->html_safe(mb_strtolower($vals['attributes']['charset'])) : '');
        }
        $res .= '</td><td class="part_desc">'.$output_mod->html_safe(decode_fld($desc)).'</td>';
        $res .= '<td class="download_link"><a href="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></td>';
    }
    if ($output_mod->get('allow_delete_attachment') && isset($vals['file_attributes']['attachment'])) {
        $res .= '<td><a href="?'.$at_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'" class="remove_attachment">'.$output_mod->trans('Remove').'</a></td>';
    }
    $res .= '</tr>';
    return $res;
}}

/*
 * Returns the modified message
 * @param int $attachment_id from get_attachment_id_for_mail_parser
 * @param string $msg raw message
 * @return string
 */
if (!hm_exists('remove_attachment')) {
    function remove_attachment($att_id, $msg) {
        $message = Message::from($msg, false);
        $message->removeAttachmentPart($att_id);

        return (string) $message;
    }
}

/*
 * ZBateson\MailMimeParser uses 0-based index for attachments
 * Which mixes embedded images and attachments
 * @param Hm_IMAP $imap Imap object
 * @param int $msg message id
 * @param string $msg_id message part
 * @return int
 */
if (!hm_exists('get_attachment_id_for_mail_parser')) {
    function get_attachment_id_for_mail_parser($imap, $uid, $msg_id) {
        $count = -1;
        $id = false;
        $struct = $imap->get_message_structure($uid);
        foreach ($struct[0]['subs'] as $key => $sub) {
            if (! empty($sub['file_attributes'])) {
                $count++;
                if ($key == $msg_id && isset($sub['file_attributes']['attachment'])) {
                    $id = $count;
                    break;
                }
            }
        }
        return $id;
    }
}

/*
 * Find a message part description/filename
 * @param array $vals bodystructure info for this message part
 * @param int $uid message number
 * @param string $part_id message part number
 * @return string
 */
if (!hm_exists('get_part_desc')) {
function get_part_desc($vals, $id, $part) {
    $desc = '';
    if (isset($vals['description']) && trim($vals['description'])) {
        $desc = $vals['description'];
    }
    elseif (isset($vals['name']) && trim($vals['name'])) {
        $desc = $vals['name'];
    }
    elseif (isset($vals['filename']) && trim($vals['filename'])) {
        $desc = $vals['filename'];
    }
    elseif (isset($vals['envelope']['subject']) && trim($vals['envelope']['subject'])) {
        $desc = $vals['envelope']['subject'];
    }
    $filename = get_imap_part_name($vals, $id, $part, true);
    if (!$desc && $filename) {
        $desc = $filename;
    }
    return $desc;
}}

/*
 * Get a human readable message size
 * @param array $vals bodystructure info for this message part
 * @return string
 */
if (!hm_exists('get_imap_size')) {
function get_imap_size($vals) {
    if (!array_key_exists('size', $vals) || !$vals['size']) {
        return '';
    }
    $size = intval($vals['size']);
    switch (true) {
        case $size > 1000:
            $size = $size/1000;
            $label = 'KB';
            break;
        case $size > 1000000:
            $size = $size/1000000;
            $label = 'MB';
            break;
        case $size > 1000000000:
            $size = $size/1000000000;
            $label = 'GB';
            break;
        default:
            $label = 'B';
    }
    return sprintf('%s %s', round($size, 2), $label);
}}

/**
 * Format the message part section of the message view page
 * @subpackage imap/functions
 * @param array $struct message structure
 * @param object $mod Hm_Output_Module
 * @param string $part currently selected message part id
 * @param string $dl_link base arguments for a download link
 * @param int $level indention level
 * @return string
 */
if (!hm_exists('format_msg_part_section')) {
function format_msg_part_section($struct, $output_mod, $part, $dl_link, $at_link, $level=0) {
    $res = '';
    $simple_view = $output_mod->get('simple_msg_part_view', false);
    $use_icons = $output_mod->get('use_message_part_icons', false);
    $mobile = $output_mod->get('is_mobile');
    if ($mobile) {
        $simple_view = true;
    }

    if(!$simple_view){
        foreach ($struct as $id => $vals) {
            if (is_array($vals) && isset($vals['type'])) {
                $row = format_msg_part_row($id, $vals, $output_mod, $level, $part, $dl_link, $at_link, $use_icons, $simple_view, $mobile);
                if (!$row) {
                    $level--;
                }
                $res .= $row;
                if (isset($vals['subs'])) {
                    $res .= format_msg_part_section($vals['subs'], $output_mod, $part, $dl_link, $at_link, ($level + 1));
                }
            }
            else {
                if (is_array($vals) && count($vals) == 1 && isset($vals['subs'])) {
                    $res .= format_msg_part_section($vals['subs'], $output_mod, $part, $dl_link, $at_link, $level);
                }
            }
        }
    }else{
        $res = format_attachment($struct, $output_mod, $part, $dl_link, $at_link);
    }
    return $res;
}}

function format_attachment($struct,  $output_mod, $part, $dl_args, $at_args) {
    $res = '';

    foreach ($struct as $id => $vals) {
        if(is_array($vals) && isset($vals['type']) && $vals['type'] != 'multipart' && isset($vals['file_attributes']) && !empty($vals['file_attributes'])) {
            $size = get_imap_size($vals);
            $desc = get_part_desc($vals, $id, $part);

            $res .= '<tr><td class="part_desc" colspan="4">'.$output_mod->html_safe(decode_fld($desc)).'</td>';
            $res .= '</td><td class="part_size">'.$output_mod->html_safe($size).'</td>';

            $res .= '<td class="download_link"><a href="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></td>';
            if ($output_mod->get('allow_delete_attachment') && isset($vals['file_attributes']['attachment'])) {
                $res .= '<td><a href="?'.$at_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'" class="remove_attachment">'.$output_mod->trans('Remove').'</a></td></tr>';
            }
        }

        if(is_array($vals) && isset($vals['subs'])) {
            $sub_res = format_attachment($vals['subs'], $output_mod, $part, $dl_args, $at_args);
            $res =$sub_res;
        }
    }

    return $res;
}

/**
 * Format the attached images section
 * @subpackage imap/functions
 * @param array $struct message structure
 * @param object $output_mod Hm_Output_Module
 * @param string $dl_link base arguments for a download link
 * @return string
 */
if (!hm_exists('format_attached_image_section')) {
function format_attached_image_section($struct, $output_mod, $dl_link) {
    $res = '';
    $isThereAnyImg = false;
    foreach ($struct as $id => $vals) {
        if ($vals['type'] === 'image') {
            $res .= '<div><img class="attached_image" src="?'.$dl_link.'&amp;imap_msg_part='.$output_mod->html_safe($id).'" ></div>';
            $isThereAnyImg = true;
        }
        if (isset($vals['subs'])) {
            $res .= format_attached_image_section($vals['subs'], $output_mod, $dl_link);
        }
    }

    if ($isThereAnyImg) {
        $res = '<div class="attached_image_box">' . $res . '</div>';
    }

    return $res;
}}

/**
 * Filter out message parts that are not attachments
 * @param array message structure
 * @return bool
 */
if (!hm_exists('filter_message_part')) {
function filter_message_part($vals) {
    if (array_key_exists('disposition', $vals) && is_array($vals['disposition']) && array_key_exists('inline', $vals['disposition'])) {
        return true;
    }
    if (array_key_exists('type', $vals) && $vals['type'] == 'multipart') {
        return true;
    }
    return false;
}}

/**
 * Sort callback to sort by internal date
 * @subpackage imap/functions
 * @param array $a first message detail
 * @param array $b second message detail
 * @return int
 */
if (!hm_exists('sort_by_internal_date')) {
function sort_by_internal_date($a, $b) {
    if ($a['internal_date'] == $b['internal_date']) return 0;
    return (strtotime($a['internal_date']) < strtotime($b['internal_date']))? -1 : 1;
}}

/**
 * Merge IMAP search results
 * @subpackage imap/functions
 * @param array $ids IMAP server ids
 * @param string $search_type
 * @param object $session session object
 * @param object $hm_cache cache object
 * @param array $folders list of folders to search
 * @param int $limit max results
 * @param array $terms list of search terms
 * @param bool $sent flag to fetch auto-bcc'ed messages
 * @return array
 */
if (!hm_exists('merge_imap_search_results')) {
function merge_imap_search_results($ids, $search_type, $session, $hm_cache, $folders = array('INBOX'), $limit=0, $terms=array(), $sent=false) {
    $msg_list = array();
    $connection_failed = false;
    $sent_results = array();
    $status = array();
    foreach($ids as $index => $id) {
        $cache = Hm_IMAP_List::get_cache($hm_cache, $id);
        $imap = Hm_IMAP_List::connect($id, $cache);
        if (imap_authed($imap)) {
            $server_details = Hm_IMAP_List::dump($id);
            $folder = $folders[$index];
            if ($sent) {
                $sent_folder = $imap->get_special_use_mailboxes('sent');
                if (array_key_exists('sent', $sent_folder)) {
                    list($sent_status, $sent_results) = merge_imap_search_results($ids, $search_type, $session, $hm_cache, array($sent_folder['sent']), $limit, $terms, false);
                    $status = array_merge($status, $sent_status);
                }
                if ($folder == 'SPECIAL_USE_CHECK') {
                    continue;
                }
            }
            if ($imap->select_mailbox($folder)) {
                $status['imap_'.$id.'_'.bin2hex($folder)] = $imap->folder_state;
                if (!empty($terms)) {
                    foreach ($terms as $term) {
                        if (preg_match('/(?:[^\x00-\x7F])/', $term[1]) === 1) {
                            $imap->search_charset = 'UTF-8';
                            break;
                        }
                    }
                    if ($sent) {
                        $msgs = $imap->search($search_type, false, $terms, array(), true, false, true);
                    }
                    else {
                        $msgs = $imap->search($search_type, false, $terms);
                    }
                }
                else {
                    $msgs = $imap->search($search_type);
                }
                if ($msgs) {
                    if ($limit) {
                        rsort($msgs);
                        $msgs = array_slice($msgs, 0, $limit);
                    }
                    foreach ($imap->get_message_list($msgs) as $msg) {
                        if (array_key_exists('content-type', $msg) && mb_stristr($msg['content-type'], 'multipart/mixed')) {
                            $msg['flags'] .= ' \Attachment';
                        }
                        if (mb_stristr($msg['flags'], 'deleted')) {
                            continue;
                        }
                        $msg['server_id'] = $id;
                        $msg['folder'] = bin2hex($folder);
                        $msg['server_name'] = $server_details['name'];
                        $msg_list[] = $msg;
                    }
                }
            }
        }
        else {
            $connection_failed = true;
        }
    }
    $session->set('imap_folder_status', $status);
    if ($connection_failed && empty($msg_list)) {
        return array(array(), false);
    }
    if (count($sent_results) > 0) {
        $msg_list = array_merge($msg_list, $sent_results);
    }
    return array($status, $msg_list);
}}

/**
 * Replace inline images in an HTML message part
 * @subpackage imap/functions
 * @param string $txt HTML
 * @param string $uid message id
 * @param array $struct message structure array
 * @param object $imap IMAP server object
 */
if (!hm_exists('add_attached_images')) {
function add_attached_images($txt, $uid, $struct, $imap) {
    if (preg_match_all("/src=('|\"|)cid:([^\s'\"]+)/", $txt, $matches)) {
        $cids = array_pop($matches);
        foreach ($cids as $id) {
            $part = $imap->search_bodystructure($struct, array('id' => $id, 'type' => 'image'), true);
            $part_ids = array_keys($part);
            $part_id = array_pop($part_ids);
            $img = $imap->get_message_content($uid, $part_id, false, $part[$part_id]);
            $txt = str_replace('cid:'.$id, 'data:image/'.$part[$part_id]['subtype'].';base64,'.base64_encode($img), $txt);
        }
    }
    return $txt;
}}

/**
 * Check for and do an Oauth2 token reset if needed
 * @subpackage imap/functions
 * @param array $server imap server data
 * @param object $config site config object
 * @return mixed
 */
if (!hm_exists('imap_refresh_oauth2_token')) {
function imap_refresh_oauth2_token($server, $config) {
    if ((int) $server['expiration'] <= time()) {
        $oauth2_data = get_oauth2_data($config);
        $details = array();
        if ($server['server'] == 'imap.gmail.com') {
            $details = $oauth2_data['gmail'];
        }
        elseif ($server['server'] == 'imap-mail.outlook.com') {
            $details = $oauth2_data['outlook'];
        }
        if (!empty($details)) {
            $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['client_uri']);
            $result = $oauth2->refresh_token($details['refresh_uri'], $server['refresh_token']);
            if (array_key_exists('access_token', $result)) {
                return array(strtotime(sprintf('+%d seconds', $result['expires_in'])), $result['access_token']);
            }
        }
    }
    return array();
}}

/**
 * Copy/Move messages on the same IMAP server
 * @subpackage imap/functions
 * @param array $ids list of message ids with server and folder info
 * @param string $action action type, copy or move
 * @param object $hm_cache system cache
 * @param array $dest_path imap id and folder to copy/move to
 * @return int count of messages moved
 */
if (!hm_exists('imap_move_same_server')) {
function imap_move_same_server($ids, $action, $hm_cache, $dest_path, $screen_emails=false) {
    $moved = array();
    $keys = array_keys($ids);
    $server_id = array_pop($keys);
    $cache = Hm_IMAP_List::get_cache($hm_cache, $server_id);
    $imap = Hm_IMAP_List::connect($server_id, $cache);
    foreach ($ids[$server_id] as $folder => $msgs) {
        if (imap_authed($imap) && $imap->select_mailbox(hex2bin($folder))) {
            if ($screen_emails) {
                foreach ($msgs as $msg) {
                    $moved[]  = sprintf('imap_%s_%s_%s', $server_id, $msg, $folder);
                    $email = current(array_column(process_address_fld($imap->get_message_headers($msg)['From']), "email"));
                    $uids = $imap->search('ALL', false, array(array('FROM', $email)));
                    foreach ($uids as $uid) {
                        if ($imap->message_action(mb_strtoupper($action), $uid, hex2bin($dest_path[2]))) {
                            $moved[]  = sprintf('imap_%s_%s_%s', $server_id, $uid, $folder);
                        }
                    }
                }
            } else {
                if ($imap->message_action(mb_strtoupper($action), $msgs, hex2bin($dest_path[2]))) {
                    foreach ($msgs as $msg) {
                        $moved[]  = sprintf('imap_%s_%s_%s', $server_id, $msg, $folder);
                    }
                }
            }

        }
    }
    return $moved;
}}

/**
 * Copy/Move messages on different IMAP servers
 * @subpackage imap/functions
 * @param array $ids list of message ids with server and folder info
 * @param string $action action type, copy or move
 * @param array $dest_path imap id and folder to copy/move to
 * @param object $hm_cache cache interface
 * @return int count of messages moved
 */
if (!hm_exists('imap_move_different_server')) {
function imap_move_different_server($ids, $action, $dest_path, $hm_cache) {
    $moved = array();
    $cache = Hm_IMAP_List::get_cache($hm_cache, $dest_path[1]);
    $dest_imap = Hm_IMAP_List::connect($dest_path[1], $cache);
    if ($dest_imap) {
        foreach ($ids as $server_id => $folders) {
            $cache = Hm_IMAP_List::get_cache($hm_cache, $server_id);
            $imap = Hm_IMAP_List::connect($server_id, $cache);
            foreach ($folders as $folder => $msg_ids) {
                if (imap_authed($imap) && $imap->select_mailbox(hex2bin($folder))) {
                    foreach ($msg_ids as $msg_id) {
                        $detail = $imap->get_message_list(array($msg_id));
                        if (array_key_exists($msg_id, $detail)) {
                            if (mb_stristr($detail[$msg_id]['flags'], 'seen')) {
                                $seen = true;
                            }
                            else {
                                $seen = false;
                            }
                        }
                        $msg = $imap->get_message_content($msg_id, 0);
                        $msg = str_replace("\r\n", "\n", $msg);
                        $msg = str_replace("\n", "\r\n", $msg);
                        $msg = rtrim($msg)."\r\n";
                        if (!$seen) {
                            $imap->message_action('UNREAD', array($msg_id));
                        }
                        if ($dest_imap->append_start(hex2bin($dest_path[2]), mb_strlen($msg), $seen)) {
                            $dest_imap->append_feed($msg."\r\n");
                            if ($dest_imap->append_end()) {
                                if ($action == 'move') {
                                    if ($imap->message_action('DELETE', array($msg_id))) {
                                        $imap->message_action('EXPUNGE', array($msg_id));
                                    }
                                }
                                $moved[] = sprintf('imap_%s_%s_%s', $server_id, $msg_id, $folder);
                            }
                        }
                    }
                }
            }
        }
    }
    return $moved;
}}

/**
 * Group info about move/copy messages
 * @subpackage imap/functions
 * @param array $form move copy input
 * @return array grouped lists of messages to move/copy
 */
if (!hm_exists('process_move_to_arguments')) {
function process_move_to_arguments($form) {
    $msg_ids = explode(',', $form['imap_move_ids']);
    $same_server_ids = array();
    $other_server_ids = array();
    $dest_path = explode('_', $form['imap_move_to']);
    if (count($dest_path) == 3 && $dest_path[0] == 'imap' && in_array($form['imap_move_action'], array('move', 'copy'), true)) {
        foreach ($msg_ids as $msg_id) {
            $path = explode('_', $msg_id);
            if (count($path) == 4 && $path[0] == 'imap') {
                if (sprintf('%s_%s', $path[0], $path[1]) == sprintf('%s_%s', $dest_path[0], $dest_path[1])) {
                    $same_server_ids[$path[1]][$path[3]][] = $path[2];
                }
                else {
                    $other_server_ids[$path[1]][$path[3]][] = $path[2];
                }
            }
        }
    }
    return array($msg_ids, $dest_path, $same_server_ids, $other_server_ids);
}}

/**
 * Get a file extension for a mime type
 * @subpackage imap/functions
 * @param string $type primary mime type
 * @param string $subtype secondary mime type
 * @todo add tons more type conversions!
 * @return string
 */
if (!hm_exists('get_imap_mime_extension')) {
function get_imap_mime_extension($type, $subtype) {
    $extension = $subtype;
    if ($type == 'multipart' || ($type == 'message' && $subtype == 'rfc822')) {
        $extension = 'eml';
    }
    if ($type == 'text') {
        switch ($subtype) {
            case 'plain':
                $extension = 'txt';
                break;
            case 'richtext':
                $extension = 'rtf';
                break;
        }
    }
    return '.'.$extension;
}}

/**
 * Try to find a filename for a message part download
 * @subpackage imap/functions
 * @param array $struct message part structure
 * @param int $uid message number
 * @param string $part_id message part number
 * @param bool $no_default don't return a default value
 * @return string
 */
if (!hm_exists('get_imap_part_name')) {
function get_imap_part_name($struct, $uid, $part_id, $no_default=false) {
    $extension = get_imap_mime_extension(mb_strtolower($struct['type']), mb_strtolower($struct['subtype']));
    if (array_key_exists('file_attributes', $struct) && is_array($struct['file_attributes']) &&
        array_key_exists('attachment', $struct['file_attributes']) && is_array($struct['file_attributes']['attachment'])) {
        for ($i=0;$i<count($struct['file_attributes']['attachment']);$i++) {
            if (mb_strtolower(trim($struct['file_attributes']['attachment'][$i])) == 'filename') {
                if (array_key_exists(($i+1), $struct['file_attributes']['attachment'])) {
                    return trim($struct['file_attributes']['attachment'][($i+1)]);
                }
            }
        }
    }

    if (array_key_exists('disposition', $struct) && is_array($struct['disposition']) && array_key_exists('attachment', $struct['disposition']) && is_array($struct['disposition']['attachment'])) {
        for ($i=0;$i<count($struct['disposition']['attachment']);$i++) {
            if (mb_strtolower(trim($struct['disposition']['attachment'][$i])) == 'filename') {
                if (array_key_exists(($i+1), $struct['disposition']['attachment'])) {
                    return trim($struct['disposition']['attachment'][($i+1)]);
                }
            }
        }
    }

    if (array_key_exists('attributes', $struct) && is_array($struct['attributes']) && array_key_exists('name', $struct['attributes'])) {
        return trim($struct['attributes']['name']);
    }
    if (array_key_exists('description', $struct) && trim($struct['description'])) {
        return trim(str_replace(array("\n", ' '), '_', $struct['description'])).$extension;
    }
    if (array_key_exists('name', $struct) && trim($struct['name'])) {
        return trim($struct['name']);
    }
    if ($no_default) {
        return '';
    }
    return 'message_'.$uid.'_part_'.$part_id.$extension;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('clear_existing_reply_details')) {
function clear_existing_reply_details($session) {
    $msgs = array();
    $max = 20;
    foreach ($session->dump() as $name => $val) {
        if (mb_substr($name, 0, 19) == 'reply_details_imap_') {
            $msgs[$name] = $val['ts'];
        }
    }
    arsort($msgs, SORT_NUMERIC);
    if (count($msgs) <= $max) {
        return ;
    }
    foreach (array_slice($msgs, $max) as $name) {
        $session->del($name);
    }
}}

/**
 * @subpackage imap/functions
 * @param object $imap imap library object
 * @return bool
 */
if (!hm_exists('imap_authed')) {
function imap_authed($imap) {
    return is_object($imap) && ($imap->get_state() == 'authenticated' || $imap->get_state() == 'selected');
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('process_sort_arg')) {
function process_sort_arg($sort, $default = 'arrival') {
    if (!$sort) {
        $default = mb_strtoupper($default);
        return array($default, true);
    }
    $rev = false;
    if (mb_substr($sort, 0, 1) == '-') {
        $rev = true;
        $sort = mb_substr($sort, 1);
    }
    $sort = mb_strtoupper($sort);
    if ($sort == 'ARRIVAL' || $sort == 'DATE') {
        $rev = $rev ? false : true;
    }
    return array($sort, $rev);
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('imap_server_type')) {
function imap_server_type($id) {
    $type = 'IMAP';
    $details = Hm_IMAP_List::dump($id);
    if (is_array($details) && array_key_exists('type', $details)) {
        $type = mb_strtoupper($details['type']);
    }
    return $type;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('get_list_headers')) {
function get_list_headers($headers) {
    $res = array();
    $list_headers = array('list-archive', 'list-unsubscribe',
        'list-subscribe', 'list-archive', 'list-post', 'list-help');
    foreach (lc_headers($headers) as $name => $val) {
        if (in_array($name, $list_headers, true)) {
            $res[mb_substr($name, 5)] = process_list_fld($val);
        }
    }
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('process_list_fld')) {
function process_list_fld($fld) {
    $res = array('links' => array(), 'email' => array(), 'values' => array());
    $fld = is_array($fld) ? implode(" ", $fld) : $fld;
    foreach (explode(',', $fld) as $val) {
        $val = trim(str_replace(array('<', '>'), '', $val));
        if (preg_match("/^http/", $val)) {
            $res['links'][] = $val;
        }
        elseif (preg_match("/^mailto/", $val)) {
            $res['email'][] = mb_substr($val, 7);
        }
        else {
            $res['values'][] = $val;
        }
    }
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('format_imap_envelope')) {
function format_imap_envelope($env, $mod) {
    $env = lc_headers($env);
    $res = '<table class="imap_envelope"><colgroup><col class="header_name_col"><col class="header_val_col"></colgroup>';
    if (array_key_exists('subject', $env) && trim($env['subject'])) {
        $res .= '<tr class="header_subject"><th colspan="2">'.$mod->html_safe($env['subject']).
            '</th></tr>';
    }

    foreach ($env as $name => $val) {
        if (in_array($name, array('date', 'from', 'to', 'message-id'), true)) {
            $res .= '<tr><th>'.$mod->html_safe(ucfirst($name)).'</th>'.
                '<td>'.$mod->html_safe($val).'</td></tr>';
        }
    }
    $res .= '</table>';
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('format_list_headers')) {
function format_list_headers($mod) {
    $res = '<tr><th>'.$mod->trans('List').'</th><td>';
    $sections = array();
    foreach ($mod->get('list_headers') as $name => $vals) {
        if (count($vals['email']) > 0 || count($vals['links']) > 0) {
            $sources = array();
            $section = ' '.$mod->html_safe($name).': ';
            foreach ($vals['email'] as $v) {
                $sources[] = '<a href="?page=compose&compose_to='.urlencode($mod->html_safe($v)).
                    '&compose_from='.$mod->get('msg_headers')['Delivered-To'].
                    '">'.$mod->trans('email').'</a>';
            }
            foreach ($vals['links'] as $v) {
                $sources[] = '<a href="'.$mod->html_safe($v).'">'.$mod->trans('link').'</a>';
            }
            $section .= implode(', ', $sources);
            $sections[] = $section;
        }
    }
    $res .= implode(' | ', $sections).'</td></tr>';
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('decode_folder_str')) {
function decode_folder_str($folder) {
    $folder_name = false;
    $parts = explode('_', $folder, 3);
    if (count($parts) == 3) {
        $folder_name = hex2bin($parts[2]);
    }
    return $folder_name;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('prep_folder_name')) {
function prep_folder_name($imap, $folder, $decode_folder=false, $parent=false) {
    if ($parent && $decode_folder) {
        $parent = decode_folder_str($parent);
    }
    if ($decode_folder) {
        $folder = decode_folder_str($folder);
    }
    $ns = get_personal_ns($imap);
    if (!$folder) {
        return false;
    }
    if ($parent && !$ns['delim']) {
        return false;
    }
    if ($parent) {
        $folder = sprintf('%s%s%s', $parent, $ns['delim'], $folder);
    }
    if ($folder && $ns['prefix'] && mb_substr($folder, 0, mb_strlen($ns['prefix'])) !== $ns['prefix']) {
        $folder = sprintf('%s%s', $ns['prefix'], $folder);
    }
    return $folder;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('get_personal_ns')) {
function get_personal_ns($imap) {
    $namespaces = $imap->get_namespaces();
    foreach ($namespaces as $ns) {
        if ($ns['class'] == 'personal') {
            return $ns;
        }
    }
    return array(
        'prefix' => false,
        'delim'=> false
    );
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('get_request_params')) {
function get_request_params($request) {
    $server_id = NULL;
    $uid = NULL;
    $folder = NULL;
    $msg_id = NULL;

    if (array_key_exists('uid', $request) && $request['uid']) {
        $uid = $request['uid'];
    }
    if (array_key_exists('list_path', $request) && preg_match("/^imap_(\w+)_(.+)/", $request['list_path'], $matches)) {
        $server_id = $matches[1];
        $folder = hex2bin($matches[2]);
    }
    if (array_key_exists('imap_msg_part', $request) && preg_match("/^[0-9\.]+$/", $request['imap_msg_part'])) {
        $msg_id = preg_replace("/^0.{1}/", '', $request['imap_msg_part']);
    }

    return [$server_id, $uid, $folder, $msg_id];
}}

if (!hm_exists('snooze_message')) {
function snooze_message($imap, $msg_id, $folder, $snooze_tag) {
    if (!$imap->select_mailbox($folder)) {
        return false;
    }
    if (!$snooze_tag) {
        $imap->message_action('UNREAD', array($msg_id));
    }
    $msg = $imap->get_message_content($msg_id, 0);
    preg_match("/^X-Snoozed:.*(\r?\n[ \t]+.*)*\r?\n?/im", $msg, $matches);
    if (count($matches)) {
        $msg = str_replace($matches[0], '', $msg);
        $old_folder = parse_snooze_header($matches[0])['from'];
    }
    if ($snooze_tag) {
        $from = $old_folder ?? $folder;
        $msg = "$snooze_tag;\n \tfrom $from\n".$msg;
    }
    $msg = str_replace("\r\n", "\n", $msg);
    $msg = str_replace("\n", "\r\n", $msg);
    $msg = rtrim($msg)."\r\n";

    $res = false;
    $snooze_folder = 'Snoozed';
    if ($snooze_tag) {
        if (!count($imap->get_mailbox_status($snooze_folder))) {
            $imap->create_mailbox($snooze_folder);
        }
        if ($imap->select_mailbox($snooze_folder) && $imap->append_start($snooze_folder, mb_strlen($msg))) {
            $imap->append_feed($msg."\r\n");
            if ($imap->append_end()) {
                if ($imap->select_mailbox($folder) && $imap->message_action('DELETE', array($msg_id))) {
                    $imap->message_action('EXPUNGE', array($msg_id));
                    $res = true;
                }
            }
        }
    } else {
        $snooze_headers = parse_snooze_header($matches[0]);
        $original_folder = $snooze_headers['from'];
        if ($imap->select_mailbox($original_folder) && $imap->append_start($original_folder, mb_strlen($msg))) {
            $imap->append_feed($msg."\r\n");
            if ($imap->append_end()) {
                if ($imap->select_mailbox($snooze_folder) && $imap->message_action('DELETE', array($msg_id))) {
                    $imap->message_action('EXPUNGE', array($msg_id));
                    $res = true;
                }
            }
        }
    }
    return $res;
}}
if (!hm_exists('add_tag_to_message')) {
function add_tag_to_message($imap, $msg_id, $folder, $tag) {
    if (!$imap->select_mailbox($folder)) {
        return false;
    }
    $msg = $imap->get_message_content($msg_id, 0);
    preg_match("/^X-Cypht-Tags:(.+)\r?\n/i", $msg, $matches);

    if (count($matches)) {
        $msg = str_replace($matches[0], '', $msg);
        $tags = explode(',', $matches[1]);
        if(in_array($tag, $tags)) {
            unset($tags[array_search(trim($tag), $tags)]);
        }else{
            $tags[] = trim($tag);
        }
    }else {
        $tags = array($tag);
    }

    $msg = "X-Cypht-Tags:".implode(',',$tags)."\n".$msg;
    $msg = str_replace("\r\n", "\n", $msg);
    $msg = str_replace("\n", "\r\n", $msg);
    $msg = rtrim($msg)."\r\n";

    $res = false;
    if ($imap->append_start($folder, strlen($msg))) {
        $imap->append_feed($msg."\r\n");
        if ($imap->append_end()) {
            if ($imap->message_action('DELETE', array($msg_id))) {
                $imap->message_action('EXPUNGE', array($msg_id));
                $res = true;
            }
        }
    }

    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('parse_snooze_header')) {
function parse_snooze_header($snooze_header)
{
    $snooze_header = str_replace('X-Snoozed: ', '', $snooze_header);
    $result = [];
    foreach (explode(';', $snooze_header) as $kv)
    {
        $kv = trim($kv);
        $spacePos = mb_strpos($kv, ' ');
        if ($spacePos > 0) {
            $result[rtrim(mb_substr($kv, 0, $spacePos), ':')] = trim(mb_substr($kv, $spacePos+1));
        } else {
            $result[$kv] = true;
        }
    }
    return $result;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('get_snooze_date')) {
function get_snooze_date($format, $only_label = false) {
    if ($format == 'later_in_day') {
        $date_string = 'today 18:00';
        $label = 'Later in the day';
    } elseif ($format == 'tomorrow') {
        $date_string = '+1 day 08:00';
        $label = 'Tomorrow';
    } elseif ($format == 'next_weekend') {
        $date_string = 'next Saturday 08:00';
        $label = 'Next weekend';
    } elseif ($format == 'next_week') {
        $date_string = 'next week 08:00';
        $label = 'Next week';
    } elseif ($format == 'next_month') {
        $date_string = 'next month 08:00';
        $label = 'Next month';
    } else {
        $date_string = $format;
        $label = 'Certain date';
    }
    $time = strtotime($date_string);
    if ($only_label) {
        return [$label, date('D, H:i', $time)];
    }
    return date('D, d M Y H:i', $time);
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('snooze_formats')) {
function snooze_formats() {
    $values = array(
        'tomorrow',
        'next_weekend',
        'next_week',
        'next_month'
    );
    if (date('H') <= 16) {
        array_push($values, 'later_in_day');
    }
    return $values;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('snooze_dropdown')) {
function snooze_dropdown($output, $unsnooze = false) {
    $values = snooze_formats();

    $txt = '<div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="dropdownMenuSnooze" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">'.$output->trans('Snooze').'</button>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuSnooze">';
    foreach ($values as $format) {
        $labels = get_snooze_date($format, true);
        $txt .= '<li><a href="#" class="snooze_helper dropdown-item d-flex justify-content-between gap-5" data-value="'.$format.'"><span>'.$output->trans($labels[0]).'</span> <span class="text-end">'.$labels[1].'</span></a></li>';
    }
    $txt .= '<li><hr class="dropdown-divider"></li>';
    $txt .= '<li><label for="snooze_input_date" class="snooze_date_picker dropdown-item cursor-pointer">'.$output->trans('Pick a date').'</label>';
    $txt .= '<input id="snooze_input_date" type="datetime-local" min="'.date('Y-m-d\Th:m').'" class="snooze_input_date" style="visibility: hidden; position: absolute; height: 0;">';
    $txt .= '<input class="snooze_input" style="display:none;"></li>';
    if ($unsnooze) {
        $txt .= '<a href="#" data-value="unsnooze" class="unsnooze snooze_helper dropdown-item"">'.$output->trans('Unsnooze').'</a>';
    }
    $txt .= '</ul></div>';

    return $txt;
}}

if (!hm_exists('tags_dropdown')) {
function tags_dropdown($context, $headers) {
    $folders = $context->get('tags', array());
    $txt = '<div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="dropdownMenuSnooze" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">'.$context->trans('Tags').'</button>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuSnooze">';

    $tags =  !empty($headers['X-Cypht-Tags']) ? explode(',', $headers['X-Cypht-Tags']) : array();
    foreach ($folders as $folder) {
        $tag = $folder['name'];
        $is_checked = in_array($folder['id'], array_map('trim', $tags));
        $txt .= '<li class="d-flex dropdown-item gap-2">';
        $txt .= '<input class="form-check-input me-1 label-checkbox" type="checkbox" value="" aria-label="..." data-id="'.$folder['id'].'" '.($is_checked ? 'checked' : '').'>';
        $txt .= '<span>'.$context->trans($tag).'</span>';
        $txt .= '</li>';
    }
    $txt .= '</ul></div>';

    return $txt;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('forward_dropdown')) {
    function forward_dropdown($output,$reply_args) {
        $txt = '<div class="dropdown d-inline-block">
                    <button type="button" class="btn btn-outline-success btn-sm dropdown-toggle" id="dropdownMenuForward" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">'.$output->trans('Forward').'</button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuForward">';
        $txt .= '<li><a href="?page=compose&amp;forward_as_attachment=1'.$reply_args.'" class="forward_link hlink dropdown-item d-flex justify-content-between gap-5" ><span>'.$output->trans('Forward as message attachment').'</a></li>';
        $txt .= '<li><a href="?page=compose&amp;forward=1'.$reply_args.'" class="forward_link hlink dropdown-item d-flex justify-content-between gap-5"><span>'.$output->trans('Edit as new message').'</a></li>';
        $txt .= '</ul></div>';
        return $txt;
    }
}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('parse_sieve_config_host')) {
function parse_sieve_config_host($host) {
    $url = parse_url($host);
    $host = $url['host'] ?? $url['path'];
    $port = $url['port'] ?? '4190';
    $scheme = $url['scheme'] ?? 'tcp://';
    $tls = $scheme === 'tls';
    // $host = '$scheme://'.$host;
    return [$host, $port, $tls];
}}

if (!hm_exists('connect_to_imap_server')) {
    function connect_to_imap_server($address, $name, $port, $user, $pass, $tls, $imap_sieve_host, $enableSieve, $type, $context, $hidden = false, $server_id = false, $show_errors = true) {
        $imap_list = array(
            'name' => $name,
            'server' => $address,
            'hide' => $hidden,
            'port' => $port,
            'user' => $user,
            'tls' => $tls);

        if (!$server_id || ($server_id && $pass)) {
            $imap_list['pass'] = $pass;
        }

        if ($type === 'jmap') {
            $imap_list['type'] = 'jmap';
            $imap_list['hide'] = $hidden;
            $imap_list['port'] = false;
            $imap_list['tls'] = false;
        }

        if ($enableSieve && $imap_sieve_host) {
            $imap_list['sieve_config_host'] = $imap_sieve_host;
        }

        if ($server_id) {
            if (Hm_IMAP_List::edit($server_id, $imap_list)) {
                $imap_server_id = $server_id;
            } else {
                return;
            }
        } else {
            $imap_server_id = Hm_IMAP_List::add($imap_list);
            if (! can_save_last_added_server('Hm_IMAP_List', $user)) {
                return;
            }
        }

        $server = Hm_IMAP_List::get($imap_server_id, true);

        if ($enableSieve &&
            $imap_sieve_host &&
            $context->module_is_supported('sievefilters') &&
            $context->user_config->get('enable_sieve_filter_setting', DEFAULT_ENABLE_SIEVE_FILTER)) {
            try {

                include_once APP_PATH.'modules/sievefilters/hm-sieve.php';
                $sieveClientFactory = new Hm_Sieve_Client_Factory();
                $client = $sieveClientFactory->init(null, $server);

                if (!$client && $show_errors) {
                    Hm_Msgs::add("ERRFailed to authenticate to the Sieve host");
                }
            } catch (Exception $e) {
                if ($show_errors) {
                    Hm_Msgs::add("ERRFailed to authenticate to the Sieve host");
                }
                if (! $server_id) {
                    Hm_IMAP_List::del($imap_server_id);
                }
                return;
            }
        }

        $imap = Hm_IMAP_List::connect($imap_server_id, false);

        if (imap_authed($imap)) {
            return $imap_server_id;
        } else {
            Hm_IMAP_List::del($imap_server_id);
            if ($show_errors) {
                Hm_Msgs::add('ERRAuthentication failed');
            }
            return null;
        }
    }
}
