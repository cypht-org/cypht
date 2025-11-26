<?php

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
function imap_sources($mod, $folder = 'sent') {
    $inbox = $mod->user_config->get('smtp_auto_bcc_setting', DEFAULT_SMTP_AUTO_BCC);
    $sources = array();
    $folder = $folder == 'drafts' ? 'draft': $folder;
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        if (array_key_exists('hide', $vals) && $vals['hide']) {
            continue;
        }
        $folders = get_special_folders($mod, $index);
        if (array_key_exists($folder, $folders) && $folders[$folder]) {
            $sources[] = array('folder' => bin2hex($folders[$folder]), 'folder_name' => $folders[$folder], 'type' => $vals['type'] ?? 'imap', 'name' => $vals['name'], 'id' => $index);
        }
        elseif ($inbox) {
            $sources[] = array('folder' => bin2hex('INBOX'), 'folder_name' => 'INBOX', 'type' => $vals['type'] ?? 'imap', 'name' => $vals['name'], 'id' => $index);
        }
        elseif ($folder=="snoozed"){
            $sources[] = array('folder' => bin2hex('Snoozed'), 'folder_name' => 'Snoozed', 'type' => $vals['type'] ?? 'imap','name' => $vals['name'],'id' => $index);
        }
        else {
            $sources[] = array('folder' => bin2hex('SPECIAL_USE_CHECK'), 'folder_name' => 'SPECIAL_USE_CHECK', 'nodisplay' => true, 'type' => $vals['type'] ?? 'imap', 'name' => $vals['name'], 'id' => $index);
        }
    }
    return $sources;
}}

/**
 * Build a source list
 * @subpackage imap/functions
 * @param array $custom user specific assignments
 * @return array
 */
if (!hm_exists('imap_data_sources')) {
function imap_data_sources($custom=array()) {
    $sources = array();
    foreach (Hm_IMAP_List::dump() as $index => $vals) {
        if (array_key_exists('hide', $vals) && $vals['hide']) {
            continue;
        }
        if (!array_key_exists('user', $vals)) {
            continue;
        }
        $mailbox = Hm_IMAP_List::get_mailbox_without_connection($vals);
        $folder = $mailbox->get_folder_name('INBOX');
        $sieve = ! empty($vals['sieve_config_host']);
        $sources[] = array('folder' => bin2hex($folder), 'folder_name' => $folder, 'type' => $vals['type'] ?? 'imap', 'name' => $vals['name'], 'id' => $index,  'sieve' => $sieve);
    }
    foreach ($custom as $path => $type) {
        $parts = explode('_', $path, 3);
        $remove_id = false;

        if ($type == 'add') {
            $details = Hm_IMAP_List::dump($parts[1]);
            if ($details) {
                $folder_name = $parts[2];
                if (! empty($details['type']) && $details['type'] == 'ews') {
                    $mailbox = Hm_IMAP_List::get_connected_mailbox($details['id']);
                    if ($mailbox && $mailbox->authed()) {
                        $folder_name = $mailbox->get_folder_name(hex2bin($folder_name));
                    }
                }
                $sources[] = array('folder' => $parts[2], 'folder_name' => $folder_name, 'type' => $details['type'] ?? 'imap', 'name' => $details['name'], 'id' => $parts[1]);
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
        $childrenCount = isset($folder['number_of_children']) ? $folder['number_of_children'] : 0;
        $results .= '<li class="'. ($folder['children'] ? 'm-has-children ' : '') .'imap_'.$id.'_'.$output_mod->html_safe($folder_name).'" data-number-children="'.$output_mod->html_safe($childrenCount).'">';

        if ($folder['children']) {
            $results .= '<div class="m-has-children-wrapper"><a href="#" class="imap_folder_link expand_link d-inline-flex" data-target="imap_'.$id.'_'.$output_mod->html_safe($folder_name).'"><i class="bi bi-plus-circle-fill"></i></a>';
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
            if (mb_strlen($folder['basename'])>15) {
                $results .= '<a ' . $attrs .
                    ' title="'.$output_mod->html_safe($folder['basename']).
                    '">'.$output_mod->html_safe(mb_substr($folder['basename'],0,15)).'...</a>';
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
        if($folder['children']) {
            $results .= '</div>';
        }
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
    $list_keyword = $output_module->get('list_keyword');
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
        $preview_msg = "";
        $type_msg = "";
        if (isset($msg['preview_msg'])) {
            $preview_msg = $msg['preview_msg'];
        }

        if (isset($msg['type_msg'])) {
            $type_msg = $msg['type_msg'];
        }
    
        if ($parent_list == 'sent') {
            $icon = 'sent';
            $from = $msg['to'];
        }
        else {
            $from = $msg['from'];
        }
        $from = format_imap_from_fld(is_array($from) ? implode(', ', $from) : $from);
        $nofrom = '';
        if (!trim($from)) {
            $from = '[No From]';
            $nofrom = ' nofrom';
        }
        $is_snoozed = !empty($msg['x_snoozed']) && hex2bin($msg['folder']) == 'Snoozed';
        $is_scheduled = !empty($msg['x_schedule']) && hex2bin($msg['folder']) == 'Scheduled';
        if ($is_snoozed) {
            $snooze_header = parse_delayed_header('X-Snoozed: '.$msg['x_snoozed'], 'X-Snoozed');
            $date = $snooze_header['until'];
            $timestamp = strtotime($date);
        } elseif ($is_scheduled) {
            $date = $msg['x_schedule'];
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
        if ($msg['folder'] && strtolower(hex2bin($msg['folder'])) != 'inbox') {
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
        if ($list_keyword) {
            $url .= '&keyword='.$output_module->html_safe($list_keyword);
        }
        if (!$show_icons) {
            $icon = false;
        }

        //if (in_array('draft', $flags)) {
        //    $url = '?page=compose&list_path='.sprintf('imap_%s_%s', $msg['server_id'], $msg['folder']).'&uid='.$msg['uid'].'&imap_draft=1';
        //}

        $msgId = $msg['message_id'] ?? '';
        $inReplyTo = $msg['in_reply_to'] ?? '';

        if ($msgId) {
            $msgId = str_replace(['<', '>'], '', trim($msgId));
        }
        if ($inReplyTo) {
            $inReplyTo = str_replace(['<', '>'], '', trim($inReplyTo));
        }

        if ($style == 'news') {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('icon_callback', $flags),
                    array('subject_callback', $subject, $url, $flags, $icon, $preview_msg, $type_msg),
                    array('safe_output_callback', 'source', $source),
                    array('safe_output_callback', 'from'.$nofrom, $from, null, str_replace(array($from, '<', '>'), '', $msg['from'])),
                    array('date_callback', $date, $timestamp, $is_snoozed || $is_scheduled),
                    array('dates_holders_callback', $msg['internal_date'], $msg['date']),
                ),
                $id,
                $style,
                $output_module,
                $row_class,
                $msgId,
                $inReplyTo
            );
        }
        else {
            $res[$id] = message_list_row(array(
                    array('checkbox_callback', $id),
                    array('safe_output_callback', 'source', $source, $icon),
                    array('safe_output_callback', 'from'.$nofrom, $from, null, str_replace(array($from, '<', '>'), '', $msg['from'])),
                    array('subject_callback', $subject, $url, $flags, null, $preview_msg, $type_msg),
                    array('date_callback', $date, $timestamp, $is_snoozed || $is_scheduled),
                    array('icon_callback', $flags),
                    array('dates_holders_callback', $msg['internal_date'], $msg['date']),
                ),
                $id,
                $style,
                $output_module,
                $row_class,
                $msgId,
                $inReplyTo
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
        $res .= '<div class="download_link"><a href="#" data-src="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></div></td>';
    }
    else {
        $res .= '</td><td class="part_size">'.$output_mod->html_safe($size);
        if (!$simple_view) {
            $res .= '</td><td class="part_encoding">'.(isset($vals['encoding']) ? $output_mod->html_safe(mb_strtolower($vals['encoding'])) : '').
                '</td><td class="part_charset">'.(isset($vals['attributes']['charset']) && trim($vals['attributes']['charset']) ? $output_mod->html_safe(mb_strtolower($vals['attributes']['charset'])) : '');
        }
        $res .= '</td><td class="part_desc">'.$output_mod->html_safe(decode_fld($desc)).'</td>';
        $res .= '<td class="download_link"><a href="#" data-src="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></td>';
    }
    if ($output_mod->get('allow_delete_attachment') && isset($vals['file_attributes']['attachment'])) {
        $res .= '<td><a href="#" data-src="?'.$at_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'" class="remove_attachment">'.$output_mod->trans('Remove').'</a></td>';
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
 * @param array $struct Imap structure
 * @param string $part_id message part
 * @return int
 */
if (!hm_exists('get_attachment_id_for_mail_parser')) {
    function get_attachment_id_for_mail_parser($struct, $part_id) {
        $count = -1;
        $id = false;
        foreach ($struct[0]['subs'] as $key => $sub) {
            if (! empty($sub['file_attributes'])) {
                $count++;
                if ($key == $part_id && isset($sub['file_attributes']['attachment'])) {
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

            $res .= '<td class="download_link"><a href="#" data-src="?'.$dl_args.'&amp;imap_msg_part='.$output_mod->html_safe($id).'">'.$output_mod->trans('Download').'</a></td>';
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
            $res .= '<div class="col-6 col-md-3">
                        <img class="attached_image img-fluid" 
                             src="?' . $dl_link . '&amp;imap_msg_part=' . $output_mod->html_safe($id) . '" >
                     </div>';
            $isThereAnyImg = true;
        }
        if (isset($vals['subs'])) {
            $res .= format_attached_image_section($vals['subs'], $output_mod, $dl_link);
        }
    }

    if ($isThereAnyImg) {
        $res = '<div class="container-fluid"><div class="row text-center attached_image_box">' . $res . '</div></div>';
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
        $mailbox = Hm_IMAP_List::get_connected_mailbox($id, $hm_cache);
        if ($mailbox && $mailbox->authed()) {
            $server_details = Hm_IMAP_List::dump($id);
            $folder = $folders[$index];
            if ($sent) {
                $sent_folder = $mailbox->get_special_use_mailboxes('sent');
                if (array_key_exists('sent', $sent_folder)) {
                    list($sent_status, $sent_results) = merge_imap_search_results($ids, $search_type, $session, $hm_cache, array($sent_folder['sent']), $limit, $terms, false);
                    $status = array_merge($status, $sent_status);
                }
                if ($folder == 'SPECIAL_USE_CHECK') {
                    continue;
                }
            }
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    if (preg_match('/(?:[^\x00-\x7F])/', $term[1]) === 1) {
                        $mailbox->set_search_charset('UTF-8');
                        break;
                    }
                }
                if ($sent) {
                    $msgs = $mailbox->search($folder, $search_type, $terms, null, null, true, true);
                }
                else {
                    $msgs = $mailbox->search($folder, $search_type, $terms);
                }
            }
            else {
                $msgs = $mailbox->search($folder, $search_type);
            }
            if ($msgs) {
                if ($limit) {
                    rsort($msgs);
                    $msgs = array_slice($msgs, 0, $limit);
                }
                foreach ($mailbox->get_message_list($folder, $msgs, !$sent) as $msg) {
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
            $status['imap_'.$id.'_'.bin2hex($folder)] = $mailbox->get_folder_state();
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

    usort($msg_list, function($a, $b) {
        return strtotime($b['internal_date']) - strtotime($a['internal_date']);
    });
    
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
        elseif ($server['server'] == 'imap-mail.office365.com') {
            $details = $oauth2_data['office365'];
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
    $moved = [];
    $responses = [];
    $keys = array_keys($ids);
    $server_id = array_pop($keys);
    $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $hm_cache);
    if ($mailbox && $mailbox->authed()) {
        foreach ($ids[$server_id] as $folder => $msgs) {
            if ($screen_emails) {
                foreach ($msgs as $msg) {
                    $moved[]  = sprintf('imap_%s_%s_%s', $server_id, $msg, $folder);
                    $email = current(array_column(process_address_fld($mailbox->get_message_headers(hex2bin($folder), $msg)['From']), "email"));
                    $uids = $mailbox->search(hex2bin($folder), 'ALL', array(array('FROM', $email)));
                    foreach ($uids as $uid) {
                        $result = $mailbox->message_action(hex2bin($folder), mb_strtoupper($action), $uid, hex2bin($dest_path[2]));
                        if ($result['status']) {
                            $response = $result['responses'][0];
                            $responses[] = [
                                'oldUid' => $uid,
                                'newUid' => $response['newUid'],
                                'oldFolder' => hex2bin($folder),
                                'newFolder' => hex2bin($dest_path[2]),
                                'oldServer' => $server_id,
                            ];
                            $moved[]  = sprintf('imap_%s_%s_%s', $server_id, $uid, $folder);
                        }
                    }
                }
            } else {
                $result = $mailbox->message_action(hex2bin($folder), mb_strtoupper($action), $msgs, hex2bin($dest_path[2]));
                if ($result['status']) {
                    foreach ($msgs as $index => $msg) {
                        $response = $result['responses'][$index];
                        $moved[]  = sprintf('imap_%s_%s_%s', $server_id, $msg, $folder);
                        $responses[] = [
                            'oldUid' => $msg,
                            'newUid' => $response['newUid'],
                            'oldFolder' => hex2bin($folder),
                            'newFolder' => hex2bin($dest_path[2]),
                            'oldServer' => $server_id,
                        ];
                    }
                }
            }

        }
    }
    return ['moved' => $moved, 'responses' => $responses];
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
    $moved = [];
    $responses = [];
    $dest_mailbox = Hm_IMAP_List::get_connected_mailbox($dest_path[1], $hm_cache);
    if ($dest_mailbox && $dest_mailbox->authed()) {
        foreach ($ids as $server_id => $folders) {
            $mailbox = Hm_IMAP_List::get_connected_mailbox($server_id, $hm_cache);
            if ($mailbox && $mailbox->authed()) {
                foreach ($folders as $folder => $msg_ids) {
                    foreach ($msg_ids as $msg_id) {
                        $detail = $mailbox->get_message_list(hex2bin($folder), array($msg_id));
                        if (array_key_exists($msg_id, $detail)) {
                            if (mb_stristr($detail[$msg_id]['flags'], 'seen')) {
                                $seen = true;
                            }
                            else {
                                $seen = false;
                            }
                        }
                        $msg = $mailbox->get_message_content(hex2bin($folder), $msg_id);
                        $msg = str_replace("\r\n", "\n", $msg);
                        $msg = str_replace("\n", "\r\n", $msg);
                        $msg = rtrim($msg)."\r\n";
                        if (!$seen) {
                            $mailbox->message_action(hex2bin($folder), 'UNREAD', array($msg_id));
                        }
                        if ($uid = $dest_mailbox->store_message(hex2bin($dest_path[2]), $msg, $seen)) {
                            if ($action == 'move') {
                                $deleteResult = $mailbox->message_action(hex2bin($folder), 'DELETE', array($msg_id));
                                if ($deleteResult['status']) {
                                    $mailbox->message_action(hex2bin($folder), 'EXPUNGE', array($msg_id));
                                }
                            }
                            $moved[] = sprintf('imap_%s_%s_%s', $server_id, $msg_id, $folder);
                            $responses[] = [
                                'oldUid' => $msg_id,
                                'newUid' => $uid,
                                'oldFolder' => hex2bin($folder),
                                'newFolder' => hex2bin($dest_path[2]),
                                'oldServer' => $server_id,
                                'newServer' => $dest_path[1],
                            ];
                        }
                    }
                }
            }
        }
    }
    return ['moved' => $moved, 'responses' => $responses];
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
if (!hm_exists('search_since_based_on_setting')) {
function search_since_based_on_setting($config) {
    if ($config->get('default_sort_order_setting', 'arrival') === 'arrival') {
        return 'SINCE';
    } else {
        return 'SENTSINCE';
    }
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
    $res = '<div class="imap_envelope d-flex flex-column border-bottom border-2 border-secondary-subtle pb-3 mb-3">';
    
    // Subject header (full width, centered)
    if (array_key_exists('subject', $env) && trim($env['subject'])) {
        $res .= '<div class="header_subject d-flex justify-content-center"><h5 class="text-center mb-0 fw-bold">'.$mod->html_safe($env['subject']).'</h5></div>';
    }

    // Other envelope headers
    foreach ($env as $name => $val) {
        if (in_array($name, array('date', 'from', 'to', 'message-id'), true)) {
            $res .= '<div class="d-flex align-items-center py-1"><span class="fw-semibold me-2 text-nowrap">'.$mod->html_safe(ucfirst($name)).':</span><span class="text-break">'.$mod->html_safe($val).'</span></div>';
        }
    }
    $res .= '</div>';
    return $res;
}}

/**
 * @subpackage imap/functions
 */
if (!hm_exists('format_list_headers')) {
function format_list_headers($mod) {
    $res = '<div class="row g-0 py-1 small_header">';
    $res .= '<div class="col-md-2 col-12"><span class="text-muted">'.$mod->trans('List').'</span></div>';
    $res .= '<div class="col-md-10 col-12">';
    $sections = array();
    foreach ($mod->get('list_headers') as $name => $vals) {
        if (count($vals['email']) > 0 || count($vals['links']) > 0) {
            $sources = array();
            $section = '<div><p class="mb-1">'.$mod->html_safe($name).':</p>';
            foreach ($vals['email'] as $v) {
                $sources[] = '<a href="?page=compose&compose_to='.urlencode($mod->html_safe($v)).
                    '&compose_from='.$mod->get('msg_headers')['Delivered-To'].
                    '" class="text-decoration-none">'.$mod->trans('email').'</a>';
            }
            foreach ($vals['links'] as $v) {
                $sources[] = '<a href="'.$mod->html_safe($v).'" class="text-decoration-none">'.$mod->trans('link').'</a>';
            }
            $section .= implode(', ', $sources).'</div>';
            $sections[] = $section;
        }
    }
    $res .= implode(' | ', $sections).'</div></div>';
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
function snooze_message($mailbox, $msg_id, $folder, $snooze_tag) {
    if (!$snooze_tag) {
        $mailbox->message_action($folder, 'UNREAD', array($msg_id));
    }
    $msg = $mailbox->get_message_content($folder, $msg_id);
    preg_match("/^X-Snoozed:.*(\r?\n[ \t]+.*)*\r?\n?/im", $msg, $matches);
    if (count($matches)) {
        $msg = str_replace($matches[0], '', $msg);
        $old_folder = parse_delayed_header($matches[0], 'X-Snoozed')['from'];
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
        $status = $mailbox->get_folder_status($snooze_folder);
        if (! count($status)) {
            $snooze_folder = $mailbox->create_folder($snooze_folder);
        } else {
            $snooze_folder = $status['id'];
        }
        if ($mailbox->store_message($snooze_folder, $msg)) {
            $deleteResult = $mailbox->message_action($folder, 'DELETE', array($msg_id));
            if ($deleteResult['status']) {
                $mailbox->message_action($folder, 'EXPUNGE', array($msg_id));
                $res = true;
            }
        }
    } else {
        $snooze_headers = parse_delayed_header($matches[0], 'X-Snoozed');
        $original_folder = $snooze_headers['from'];
        if ($mailbox->store_message($original_folder, $msg)) {
            $deleteResult = $mailbox->message_action($snooze_folder, 'DELETE', array($msg_id));
            if ($deleteResult['status']) {
                $mailbox->message_action($snooze_folder, 'EXPUNGE', array($msg_id));
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
    $values = nexter_formats();

    $txt = '<div class="dropdown d-inline-block">
                <a class="hlink text-decoration-none btn btn-sm btn-outline-secondary dropdown-toggle" id="dropdownMenuSnooze" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true" data-bs-auto-close="outside">'.$output->trans('Snooze').'</a>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuSnooze">';
    foreach ($values as $format) {
        $labels = get_scheduled_date($format, true);
        $txt .= '<li><a href="#" class="nexter_date_helper_snooze dropdown-item gap-5" data-value="'.$format.'"><span>'.$output->trans($labels[0]).'</span> <span class="text-end">'.$labels[1].'</span></a></li>';
    }
    $txt .= '<li><hr class="dropdown-divider"></li>';
    $txt .= '<li><label for="nexter_input_date_snooze" class="nexter_date_picker_snooze dropdown-item cursor-pointer">'.$output->trans('Pick a date').'</label>';
    $txt .= '<input id="nexter_input_date_snooze" type="datetime-local" min="'.date('Y-m-d\Th:m').'" class="nexter_input_date_snooze" style="visibility: hidden; position: absolute; height: 0;">';
    $txt .= '<input class="nexter_input_snooze" style="display:none;"></li>';
    if ($unsnooze) {
        $txt .= '<a href="#" data-value="unsnooze" class="unsnooze nexter_date_helper_snooze dropdown-item"">'.$output->trans('Unsnooze').'</a>';
    }
    $txt .= '</ul></div>';

    return $txt;
}}

if (!hm_exists('tags_dropdown')) {
function tags_dropdown($context) {
    $msgUid = $context->get('msg_text_uid');
    $msgTags = Hm_Tags::getTagIdsWithMessage($msgUid);

    $folders = $context->get('tags', array());
    $txt = '<div class="dropdown d-inline-block">
                <a class="hlink text-decoration-none btn btn-sm btn-outline-secondary dropdown-toggle" id="dropdownMenuTag" data-bs-toggle="dropdown" aria-haspopup="true" href="#" aria-expanded="true">'.$context->trans('Tags').'</a>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuTag">';

    foreach ($folders as $folder) {
        $tag = $folder['name'];
        $is_checked = in_array($folder['id'], $msgTags);
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
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" id="dropdownMenuForward" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">'.$output->trans('Forward').'</button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuForward">';
        $txt .= '<li><a href="?page=compose&amp;forward_as_attachment=1'.$reply_args.'" class="forward_link hlink dropdown-item d-flex justify-content-between gap-5 text-decoration-none" ><span>'.$output->trans('Forward as message attachment').'</a></li>';
        $txt .= '<li><a href="?page=compose&amp;forward=1'.$reply_args.'" class="forward_link hlink dropdown-item d-flex justify-content-between gap-5 text-decoration-none"><span>'.$output->trans('Edit as new message').'</a></li>';
        $txt .= '</ul></div>';
        return $txt;
    }
}

if (!hm_exists('connect_to_imap_server')) {
    function connect_to_imap_server($address, $name, $port, $user, $pass, $tls, $imap_sieve_host, $enableSieve, $type, $context, $hidden = false, $server_id = false, $sieve_tls = false, $show_errors = true) {
        $imap_list = array(
            'name' => $name,
            'server' => $address,
            'type' => $type,
            'hide' => $hidden,
            'port' => $port,
            'user' => $user,
            'tls' => $tls);

        if (!$server_id || ($server_id && $pass)) {
            $imap_list['pass'] = $pass;
        }

        if ($type === 'jmap') {
            $imap_list['port'] = false;
            $imap_list['tls'] = false;
        }

        if ($enableSieve && $imap_sieve_host) {
            $imap_list['sieve_config_host'] = $imap_sieve_host;
            $imap_list['sieve_tls'] = $sieve_tls;
        }
        if ($server_id) {
            if (Hm_IMAP_List::edit($server_id, $imap_list)) {
                $imap_server_id = $server_id;
            } else {
                return;
            }
        } else {
            $imap_server_id = Hm_IMAP_List::add($imap_list);
            if ($type != 'ews' && ! can_save_last_added_server('Hm_IMAP_List', $user)) {
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
                $client = $sieveClientFactory->init(null, $server, $context->module_is_supported('nux'));

                if (!$client && $show_errors) {
                    Hm_Msgs::add("Failed to authenticate to the Sieve host", "warning");
                }
            } catch (Exception $e) {
                if ($show_errors) {
                    Hm_Msgs::add("Failed to authenticate to the Sieve host", "warning");
                }
                if (! $server_id) {
                    Hm_IMAP_List::del($imap_server_id);
                }
                return;
            }
        }

        $mailbox = Hm_IMAP_List::connect($imap_server_id, false);

        if ($mailbox->authed()) {
            return $imap_server_id;
        } else {
            Hm_IMAP_List::del($imap_server_id);
            if ($show_errors) {
                Hm_Msgs::add('Authentication failed', 'warning');
            }
            return null;
        }
    }
}

if (!hm_exists('save_sent_msg')) {
function save_sent_msg($handler, $imap_id, $mailbox, $imap_details, $msg, $msg_id, $show_errors = true) {
    $specials = get_special_folders($handler, $imap_id);
    $sent_folder = false;
    if (array_key_exists('sent', $specials) && $specials['sent']) {
        $sent_folder = $specials['sent'];
    }

    if (!$sent_folder) {
        $auto_sent = $mailbox->get_special_use_mailboxes('sent');
        if (!array_key_exists('sent', $auto_sent)) {
            return;
        }
        $sent_folder = $auto_sent['sent'];
    }
    if (!$sent_folder) {
        Hm_Debug::add(sprintf("Unable to save sent message, no sent folder for server %s %s", $mailbox->server_type(), $imap_details['server']));
    }
    $uid = null;
    if ($sent_folder) {
        Hm_Debug::add(sprintf("Attempting to save sent message for server %s in folder %s", $mailbox->server_type(), $imap_details['server'], $sent_folder));
        $uid = $mailbox->store_message($sent_folder, $msg);
        if (! $uid) {
            Hm_Msgs::add('ERRAn error occurred saving the sent message');
        }
    }
    return [$uid, $sent_folder];
}}

if (!hm_exists('is_imap_archive_folder')) {
function is_imap_archive_folder($server_id, $user_config, $current_folder) {
    $special_folders = $user_config->get('special_imap_folders', array());
    
    if (isset($special_folders[$server_id]['archive'])) {
        $archive_folder = $special_folders[$server_id]['archive'];
        if (bin2hex($archive_folder) == $current_folder) {
            return true;
        }
    }
    
    return false;
}}

/**
 * Error messages from spam reporting services
 * @subpackage imap/functions
 * @param string $error_msg Raw error message from service
 * @return string User-friendly error message
 */
if (!hm_exists('normalize_spam_report_error')) {
function normalize_spam_report_error($error_msg) {
    $error_mappings = array(
        // SpamCop error mappings
        'not enabled' => 'SpamCop reporting is not enabled. Please enable it in Settings.',
        'not configured' => 'SpamCop submission email is not configured. Please configure it in Settings.',
        'submission email' => 'SpamCop submission email is not configured. Please configure it in Settings.',
        'sender email' => 'No sender email address configured. Please configure it in Settings.',
        'No sender' => 'No sender email address configured. Please configure it in Settings.',
        'Failed to send email' => 'Failed to send email to SpamCop. Please check your server mail configuration.',
        'send email' => 'Failed to send email to SpamCop. Please check your server mail configuration.',
        
        // AbuseIPDB error mappings
        'AbuseIPDB reporting is not enabled' => 'AbuseIPDB reporting is not enabled. Please enable it in Settings.',
        'AbuseIPDB API key not configured' => 'AbuseIPDB API key is not configured. Please configure it in Settings.',
        'AbuseIPDB API key' => 'AbuseIPDB API key is not configured. Please configure it in Settings.',
        'AbuseIPDB API key is invalid' => 'AbuseIPDB API key is invalid. Please check your API key in Settings.',
        'Could not extract IP address' => 'Could not extract IP address from message. The email may not contain valid IP information.',
        'Could not extract IP address from message' => 'Could not extract IP address from message. The email may not contain valid IP information.',
        'Failed to connect to AbuseIPDB' => 'Failed to connect to AbuseIPDB. Please check your internet connection.',
        'AbuseIPDB rate limit exceeded' => 'AbuseIPDB rate limit exceeded. Please try again later.',
        'AbuseIPDB rate limit cooldown active' => 'AbuseIPDB rate limit cooldown active. Please wait before trying again.',
        'AbuseIPDB validation error' => 'AbuseIPDB validation error. Please check your API key and configuration.',
        'AbuseIPDB error' => 'An error occurred while reporting to AbuseIPDB. Please try again later.',
        'Invalid response from AbuseIPDB' => 'Invalid response from AbuseIPDB. Please try again later.',
        'cURL error' => 'Failed to connect to AbuseIPDB. Please check your internet connection.'
    );
    
    foreach ($error_mappings as $key => $message) {
        if (strpos($error_msg, $key) !== false) {
            return $message;
        }
    }
    
    return $error_msg;
}}

/**
 * Report spam message to SpamCop
 * Uses authenticated SMTP to ensure proper SPF/DKIM validation
 * Must use the exact email address from the IMAP server where the message is located
 */
if (!hm_exists('report_spam_to_spamcop')) {
function report_spam_to_spamcop($message_source, $reasons, $user_config, $session = null, $imap_server_email = '') {
    $spamcop_enabled = $user_config->get('spamcop_enabled_setting', false);
    if (!$spamcop_enabled) {
        return array('success' => false, 'error' => 'SpamCop reporting is not enabled');
    }

    $spamcop_email = $user_config->get('spamcop_submission_email_setting', '');
    if (empty($spamcop_email)) {
        return array('success' => false, 'error' => 'SpamCop submission email not configured');
    }

    $sanitized_message = sanitize_message_for_spam_report($message_source, $user_config);

    // SpamCop requires the exact email address associated with the account
    $from_email = '';
    if (!empty($imap_server_email)) {
        $from_email = $imap_server_email;
    } else {
        // Fallback: try to get from spamcop_from_email_setting
        $from_email = $user_config->get('spamcop_from_email_setting', '');
        if (empty($from_email)) {
            // or else get from the first IMAP server
            $imap_servers = $user_config->get('imap_servers', array());
            if (!empty($imap_servers)) {
                $first_server = reset($imap_servers);
                $from_email = isset($first_server['user']) ? $first_server['user'] : '';
            }
        }
    }

    if (empty($from_email)) {
        return array('success' => false, 'error' => 'No sender email address configured');
    }

    $subject = 'Spam report';

    if (!class_exists('Hm_MIME_Msg')) {
        $mime_file = (defined('APP_PATH') ? APP_PATH : dirname(__FILE__) . '/../') . 'modules/smtp/hm-mime-message.php';
        if (file_exists($mime_file)) {
            require_once $mime_file;
        } else {
            return array('success' => false, 'error' => 'SMTP module required for SpamCop reporting. Please enable the SMTP module.');
        }
    }
    
    // Create temporary file for the spam message attachment
    $file_dir = $user_config->get('attachment_dir', sys_get_temp_dir());
    if (!is_dir($file_dir)) {
        $file_dir = sys_get_temp_dir();
    }
    // Create subdirectory for user if using attachment_dir
    if ($file_dir !== sys_get_temp_dir() && $session) {
        $user_dir = $file_dir . DIRECTORY_SEPARATOR . md5($session->get('username', 'default'));
        if (!is_dir($user_dir)) {
            @mkdir($user_dir, 0755, true);
        }
        $file_dir = $user_dir;
    }
    $temp_file = tempnam($file_dir, 'spamcop_');
    
    // format it like forward as attachment does
    if (class_exists('Hm_Crypt') && class_exists('Hm_Request_Key')) {
        $encrypted_content = Hm_Crypt::ciphertext($sanitized_message, Hm_Request_Key::generate());
        file_put_contents($temp_file, $encrypted_content);
    } else {
        file_put_contents($temp_file, $sanitized_message);
    }
    
    // Build MIME message
    $body = '';
    $mime = new Hm_MIME_Msg($spamcop_email, $subject, $body, $from_email, false, '', '', '', '', $from_email);
    
    $attachment = array(
        'name' => 'spam.eml',
        'type' => 'message/rfc822',
        'size' => strlen($sanitized_message),
        'filename' => $temp_file
    );
    
    $mime->add_attachments(array($attachment));

    $mime_message = $mime->get_mime_msg();
    
    // SpamCop rejects automated submissions, so removed X-Mailer headers
    $mime_message = preg_replace('/^X-Mailer:.*$/mi', '', $mime_message);
    $mime_message = preg_replace('/\r\n\r\n+/', "\r\n\r\n", $mime_message); // Clean up extra blank lines
    
    // Extract boundary and fix encoding (Hm_MIME_Msg uses 7bit for message/rfc822, SpamCop requires base64)
    $parts = explode("\r\n\r\n", $mime_message, 2);
    $mime_body = isset($parts[1]) ? $parts[1] : '';
    
    // Extract boundary from body (Hm_MIME_Msg creates its own boundary)
    $boundary = '';
    if (preg_match('/^--([A-Za-z0-9]+)/m', $mime_body, $boundary_match)) {
        $boundary = $boundary_match[1];
    }
    
    // Fix encoding from 7bit to base64 for message/rfc822 attachment
    if (!empty($boundary)) {
        $pattern = '/(--' . preg_quote($boundary, '/') . '\r\nContent-Type: message\/rfc822[^\r\n]*\r\n(?:[^\r\n]*\r\n)*?Content-Transfer-Encoding: )7bit(\r\n\r\n)(.*?)(\r\n--' . preg_quote($boundary, '/') . '(?:--)?)/s';
        
        if (preg_match($pattern, $mime_message, $matches)) {
            $attachment_content = rtrim($matches[3], "\r\n");
            $encoded_content = chunk_split(base64_encode($attachment_content));
            $mime_message = preg_replace($pattern, '$1base64$2' . $encoded_content . '$4', $mime_message);
        } elseif (defined('DEBUG_MODE') && DEBUG_MODE) {
            Hm_Debug::add('SpamCop: Warning - Could not fix encoding from 7bit to base64', 'warning');
        }
    }
    
    @unlink($temp_file);
    
    $parts = explode("\r\n\r\n", $mime_message, 2);
    $all_headers = isset($parts[0]) ? $parts[0] : '';
    $mime_body = isset($parts[1]) ? $parts[1] : '';
    
    // Extract boundary again if needed (after encoding fix)
    if (empty($boundary) && preg_match('/^--([A-Za-z0-9]+)/m', $mime_body, $boundary_match)) {
        $boundary = $boundary_match[1];
    }

    $headers = array();
    $header_lines = explode("\r\n", $all_headers);
    foreach ($header_lines as $line) {
        if (preg_match('/^(From|Reply-To|MIME-Version|Content-Type):/i', $line)) {
            if (preg_match('/^Content-Type:/i', $line) && !empty($boundary)) {
                $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            } else {
                $headers[] = $line;
            }
        }
    }

    if (!class_exists('Hm_SMTP_List')) {
        $smtp_file = (defined('APP_PATH') ? APP_PATH : dirname(__FILE__) . '/../') . 'modules/smtp/hm-smtp.php';
        if (file_exists($smtp_file)) {
            require_once $smtp_file;
        }
    }
    
    if ($session !== null && class_exists('Hm_SMTP_List')) {
        try {
            Hm_SMTP_List::init($user_config, $session);
            $smtp_servers = Hm_SMTP_List::dump();
            $smtp_id = false;
            foreach ($smtp_servers as $id => $server) {
                if (isset($server['user']) && strtolower(trim($server['user'])) === strtolower(trim($from_email))) {
                    $smtp_id = $id;
                    break;
                }
            }

            // if ($smtp_id === false && !empty($smtp_servers)) {
            //     $smtp_id = key($smtp_servers);
            // }
            
            if ($smtp_id !== false) {
                $mailbox = Hm_SMTP_List::connect($smtp_id, false);
                if ($mailbox && $mailbox->authed()) {
                    $smtp_headers = array();
                    $smtp_headers[] = 'From: ' . $from_email;
                    $smtp_headers[] = 'Reply-To: ' . $from_email;
                    $smtp_headers[] = 'To: ' . $spamcop_email;
                    $smtp_headers[] = 'Subject: ' . $subject;
                    $smtp_headers[] = 'MIME-Version: 1.0';
                    if (!empty($boundary)) {
                        $smtp_headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
                    }
                    $smtp_headers[] = 'Date: ' . date('r');
                    $smtp_headers[] = 'Message-ID: <' . md5(uniqid(rand(), true)) . '@' . php_uname('n') . '>';
                    
                    $smtp_message = implode("\r\n", $smtp_headers) . "\r\n\r\n" . $mime_body;
                    
                    $err_msg = $mailbox->send_message($from_email, array($spamcop_email), $smtp_message);
                    
                    if ($err_msg === false) {
                        return array('success' => true);
                    } elseif (defined('DEBUG_MODE') && DEBUG_MODE) {
                        Hm_Debug::add(sprintf('SpamCop: SMTP send failed: %s', $err_msg), 'warning');
                    }
                } elseif (defined('DEBUG_MODE') && DEBUG_MODE) {
                    Hm_Debug::add(sprintf('SpamCop: SMTP connection failed for server ID %s', $smtp_id), 'warning');
                }
            }
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Hm_Debug::add(sprintf('SpamCop: SMTP exception: %s', $e->getMessage()), 'error');
            }
        }
    }
    
    // Fallback to mail() if SMTP is not available
    $timeout = 10;
    $old_timeout = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', $timeout);

    try {
        $mail_sent = @mail($spamcop_email, $subject, $mime_body, implode("\r\n", $headers));
        
        ini_set('default_socket_timeout', $old_timeout);
        
        if ($mail_sent) {
            return array('success' => true);
        } else {
            $error = 'Failed to send email to SpamCop. Please ensure your server has valid SPF/DKIM records or configure an SMTP server.';
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                Hm_Debug::add('SpamCop: mail() function failed', 'error');
            }
            return array('success' => false, 'error' => $error);
        }
    } catch (Exception $e) {
        ini_set('default_socket_timeout', $old_timeout);
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            Hm_Debug::add(sprintf('SpamCop: Exception in mail(): %s', $e->getMessage()), 'error');
        }
        return array('success' => false, 'error' => $e->getMessage());
    }
}}

/**
 * Sanitize message source for spam reporting
 */
if (!hm_exists('sanitize_message_for_spam_report')) {
function sanitize_message_for_spam_report($message_source, $user_config) {
    $user_emails = array();
    $imap_servers = $user_config->get('imap_servers', array());
    foreach ($imap_servers as $server) {
        if (isset($server['user'])) {
            $user_emails[] = strtolower($server['user']);
        }
    }

    $parts = explode("\r\n\r\n", $message_source, 2);
    $headers = isset($parts[0]) ? $parts[0] : '';
    $body = isset($parts[1]) ? $parts[1] : '';

    if (!empty($user_emails)) {
        foreach ($user_emails as $email) {
            // Remove email from various headers
            $headers = preg_replace('/\b' . preg_quote($email, '/') . '\b/i', '[REDACTED]', $headers);
        }
    }

    $sensitive_headers = array('X-Original-From', 'X-Forwarded-For', 'X-Real-IP');
    foreach ($sensitive_headers as $header) {
        $headers = preg_replace('/^' . preg_quote($header, '/') . ':.*$/mi', '', $headers);
    }

    // Clean blank lines
    $headers = preg_replace('/\r\n\r\n+/', "\r\n\r\n", $headers);

    return $headers . "\r\n\r\n" . $body;
}
}

/**
 * Extract IP address from email message headers
 * @param string $message_source Full email message source
 * @return string|false IP address (IPv4 or IPv6) or false if not found
 */
if (!hm_exists('extract_ip_from_message')) {
function extract_ip_from_message($message_source) {
    $parts = explode("\r\n\r\n", $message_source, 2);
    $headers = isset($parts[0]) ? $parts[0] : '';
    
    if (empty($headers)) {
        return false;
    }

    $header_lines = explode("\r\n", $headers);
    $received_headers = array();
    $current_header = '';
    
    foreach ($header_lines as $line) {
        if (preg_match('/^Received:/i', $line)) {
            if (!empty($current_header)) {
                $received_headers[] = $current_header;
            }
            $current_header = $line;
        } elseif (!empty($current_header) && preg_match('/^\s+/', $line)) {
            $current_header .= ' ' . trim($line);
        } elseif (!empty($current_header)) {
            $received_headers[] = $current_header;
            $current_header = '';
        }
    }
    if (!empty($current_header)) {
        $received_headers[] = $current_header;
    }

    $valid_ips = array();
    
    foreach (array_reverse($received_headers) as $received) {
        // Pattern 1: from [IP] or from hostname [IP] (most common)
        // Matches: "from [192.168.1.1]" or "from mail.example.com [192.168.1.1]"
        if (preg_match('/from\s+(?:[^\s]+\s+)?\[?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\]?/i', $received, $matches)) {
            $candidate = $matches[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $valid_ips[] = $candidate;
            }
        }
        
        // Pattern 2: by hostname ([IP])
        // Matches: "by mail.example.com ([192.168.1.1])"
        if (preg_match('/by\s+[^\s]+\s+\(\[?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\]?\)/i', $received, $matches)) {
            $candidate = $matches[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $valid_ips[] = $candidate;
            }
        }
        
        // Pattern 3: IPv6 addresses
        // Matches: "from [2001:db8::1]" or "from [::1]"
        if (preg_match('/from\s+(?:[^\s]+\s+)?\[?([0-9a-f:]+)\]?/i', $received, $matches)) {
            $candidate = trim($matches[1], '[]');
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $valid_ips[] = $candidate;
            }
        }
        
        // Pattern 4: Generic IP pattern (fallback for edge cases)
        // Matches any valid-looking IP in the header
        if (preg_match('/\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/', $received, $matches)) {
            $candidate = $matches[1];
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                // Avoid duplicates
                if (!in_array($candidate, $valid_ips)) {
                    $valid_ips[] = $candidate;
                }
            }
        }
    }
    
    // THe original sender, will be the first valid founded since we checked in reverse
    if (!empty($valid_ips)) {
        return $valid_ips[0];
    }

    $fallback_headers = array('X-Originating-IP', 'X-Forwarded-For', 'X-Real-IP');
    foreach ($fallback_headers as $header_name) {
        if (preg_match('/^' . preg_quote($header_name, '/') . ':\s*(.+)$/mi', $headers, $matches)) {
            $ip = trim($matches[1]);
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Remove port if present
            if (strpos($ip, ':') !== false && !preg_match('/^\[.*\]$/', $ip)) {
                $ip_parts = explode(':', $ip);
                $ip = $ip_parts[0];
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return false;
}
}

/**
 * Report spam message to AbuseIPDB
 * @param string $message_source Full email message source
 * @param array $reasons Array of spam reasons selected by user
 * @param object $user_config User configuration object
 * @return array Result array with 'success' (bool) and 'error' (string) keys
 */
if (!hm_exists('report_spam_to_abuseipdb')) {
function report_spam_to_abuseipdb($message_source, $reasons, $user_config) {
    $enabled = $user_config->get('abuseipdb_enabled_setting', false);
    if (!$enabled) {
        return array('success' => false, 'error' => 'AbuseIPDB reporting is not enabled');
    }

    $api_key = $user_config->get('abuseipdb_api_key_setting', '');
    if (empty($api_key)) {
        return array('success' => false, 'error' => 'AbuseIPDB API key not configured');
    }

    $rate_limit_key = 'abuseipdb_rate_limit_timestamp';
    $rate_limit_timestamp = $user_config->get($rate_limit_key, 0);
    $rate_limit_cooldown = 15 * 60;
    if ($rate_limit_timestamp > 0 && (time() - $rate_limit_timestamp) < $rate_limit_cooldown) {
        $remaining_minutes = ceil(($rate_limit_cooldown - (time() - $rate_limit_timestamp)) / 60);
        return array('success' => false, 'error' => sprintf('AbuseIPDB rate limit cooldown active. Please wait %d more minute(s) before trying again.', $remaining_minutes));
    }

    $ip = extract_ip_from_message($message_source);
    if (!$ip) {
        return array('success' => false, 'error' => 'Could not extract IP address from message');
    }

    $comment = implode(', ', $reasons);
    if (empty($comment)) {
        $comment = 'Spam email reported via Cypht';
    }
    
    $data = array(
        'ip' => $ip,
        'categories' => '11', // Category 11 = Email Spam (spam email content, infected attachments, and phishing emails)
        'comment' => $comment
    );

    $ch = curl_init('https://api.abuseipdb.com/api/v2/report');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Key: ' . $api_key
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if ($curl_error || $curl_errno !== 0) {
        // Include HTTP code if available, otherwise just cURL error
        $error_msg = 'Failed to connect to AbuseIPDB';
        if ($http_code > 0) {
            $error_msg .= sprintf(' (HTTP %d)', $http_code);
        }
        if ($curl_error) {
            $error_msg .= ': ' . $curl_error;
        } elseif ($curl_errno !== 0) {
            $error_msg .= sprintf(' (cURL error %d)', $curl_errno);
        }
        return array('success' => false, 'error' => $error_msg);
    }
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data']['ipAddress'])) {
            $user_config->set($rate_limit_key, 0);
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => 'Invalid response from AbuseIPDB');
        }
    } elseif ($http_code === 429) {
        // Rate limit exceeded - store timestamp to prevent immediate re-attempts
        $user_config->set($rate_limit_key, time());
        
        return array('success' => false, 'error' => 'AbuseIPDB rate limit exceeded. Please try again later.');
    } elseif ($http_code === 422) {
        $result = json_decode($response, true);
        $error_detail = 'Invalid request to AbuseIPDB';
        if (isset($result['errors'][0]['detail'])) {
            $error_detail = $result['errors'][0]['detail'];
        } elseif (isset($result['errors'][0]['title'])) {
            $error_detail = $result['errors'][0]['title'];
        }
        return array('success' => false, 'error' => 'AbuseIPDB validation error: ' . $error_detail);
    } elseif ($http_code === 401) {
        return array('success' => false, 'error' => 'AbuseIPDB API key is invalid. Please check your API key in Settings.');
    } else {
        $result = json_decode($response, true);
        $error_detail = sprintf('Failed to report to AbuseIPDB (HTTP %d)', $http_code);
        if (isset($result['errors'][0]['detail'])) {
            $error_detail = $result['errors'][0]['detail'];
        } elseif (isset($result['errors'][0]['title'])) {
            $error_detail = $result['errors'][0]['title'];
        }
        return array('success' => false, 'error' => 'AbuseIPDB error: ' . $error_detail);
    }
}
}