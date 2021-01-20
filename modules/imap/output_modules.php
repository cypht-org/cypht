<?php

/**
 * IMAP modules
 * @package modules
 * @subpackage imap
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Format a custom list controls section
 * @subpackage imap/output
 */
class Hm_Output_imap_custom_controls extends Hm_Output_Module {
    /**
     * Adds list controls to the IMAP folder page view
     */
    protected function output() {
        if ($this->get('custom_list_controls_type')) {
            $filter = $this->get('list_filter');
            $sort = $this->get('list_sort');
            $keyword = $this->get('list_keyword');
            $opts = array('all' => $this->trans('All'), 'unseen' => $this->trans('Unread'),
                'seen' => $this->trans('Read'), 'flagged' => $this->trans('Flagged'),
                'unflagged' => $this->trans('Unflagged'), 'answered' => $this->trans('Answered'),
                'unanswered' => $this->trans('Unanswered'));

            $default_sort_order = $this->get('default_sort_order');
            if ($default_sort_order == 'arrival') {
                $sorts = array('arrival' => $this->trans('Arrival Date'), 'from' => $this->trans('From'),
                    'to' => $this->trans('To'), 'subject' => $this->trans('Subject'), 'date' => $this->trans('Sent Date'));
            } else {
                $sorts = array('date' => $this->trans('Sent Date'), 'from' => $this->trans('From'),
                    'to' => $this->trans('To'), 'subject' => $this->trans('Subject'), 'arrival' => $this->trans('Arrival Date'));
            }

            if (!$this->get('is_mobile', false)) {
                $custom = '<form id="imap_filter_form" method="GET">';
                $custom .= '<input type="hidden" name="page" value="message_list" />';
                $custom .= '<input type="hidden" name="list_path" value="'.$this->html_safe($this->get('list_path')).'" />';
                $custom .= '<input type="search" placeholder="'.$this->trans('Search').
                    '" class="imap_keyword" name="keyword" value="'.$this->html_safe($keyword).'" />';
                $custom .= '<select name="sort" class="imap_sort">';
                foreach ($sorts as $name => $val) {
                    $custom .= '<option ';
                    if ($name == $sort) {
                        $custom .= 'selected="selected" ';
                    }
                    $custom .= 'value="'.$name.'">'.$val.' &darr;</option>';
                    $custom .= '<option ';
                    if ('-'.$name == $sort) {
                        $custom .= 'selected="selected" ';
                    }
                    $custom .= 'value="-'.$name.'">'.$val.' &uarr;</option>';
                }
                $custom .= '</select>';

                $custom .= '<select name="filter" class="imap_filter">';
                foreach ($opts as $name => $val) {
                    $custom .= '<option ';
                    if ($name == $filter) {
                        $custom .= 'selected="selected" ';
                    }
                    $custom .= 'value="'.$name.'">'.$val.'</option>';
                }
                $custom .= '</select></form>';
            }
            if ($this->get('custom_list_controls_type') == 'remove') {
                $custom .= '<a class="remove_source" title="'.$this->trans('Remove this folder from combined pages').
                    '" href=""><img width="20" height="20" class="refresh_list" src="'.Hm_Image_Sources::$circle_x.
                    '" alt="'.$this->trans('Remove').'"/></a><a style="display: none;" class="add_source" title="'.
                    $this->trans('Add this folder to combined pages').'" href=""><img class="refresh_list" width="20" height="20" alt="'.
                    $this->trans('Add').'" src="'.Hm_Image_Sources::$circle_check.'" /></a>';
            }
            else {
                $custom .= '<a style="display: none;" class="remove_source" title="'.$this->trans('Remove this folder from combined pages').
                    '" href=""><img width="20" height="20" class="refresh_list" src="'.Hm_Image_Sources::$circle_x.'" alt="'.
                    $this->trans('Remove').'"/></a><a class="add_source" title="'.$this->trans('Add this folder to combined pages').
                    '" href=""><img class="refresh_list" width="20" height="20" alt="'.$this->trans('Add').'" src="'.
                    Hm_Image_Sources::$circle_check.'" /></a>';
            }
            $this->out('custom_list_controls', $custom);
        }
    }
}

/**
 * Format a message part body for display
 * @subpackage imap/output
 */
class Hm_Output_filter_message_body extends Hm_Output_Module {
    /**
     * Format html, text, or image content
     */
    protected function output() {
        $txt = '<div class="msg_text_inner">';
        if ($this->get('msg_text')) {
            $struct = $this->get('msg_struct_current', array());
            if (array_key_exists('envelope', $struct) && is_array($struct['envelope']) && count($struct['envelope']) > 0) {
                $txt .= format_imap_envelope($struct['envelope'], $this);
            }
            if (isset($struct['subtype']) && strtolower($struct['subtype']) == 'html') {
                $allowed = $this->get('header_allow_images');
                $images = $this->get('imap_allow_images', false);
                if ($allowed && stripos($this->get('msg_text'), 'img')) {
                    if (!$images) {
                        $id = $this->get('imap_msg_part');
                        $txt .= '<div class="allow_image_link">'.
                            '<a href="#" class="msg_part_link" data-allow-images="1" '.
                            'data-message-part="'.$this->html_safe($id).'">'.
                            $this->trans('Allow Images').'</a></div>';
                    }
                }
                $txt .= format_msg_html($this->get('msg_text'), $images);
            }
            elseif (isset($struct['type']) && strtolower($struct['type']) == 'image') {
                $txt .= format_msg_image($this->get('msg_text'), strtolower($struct['subtype']));
            }
            else {
                if ($this->get('imap_msg_part') === "0") {
                    $txt .= format_msg_text($this->get('msg_text'), $this, false);
                }
                else {
                    $txt .= format_msg_text($this->get('msg_text'), $this);
                }
            }
        }
        $txt .= '</div>';
        $this->out('msg_text', $txt);
    }
}

/**
 * Format the message part section of the message view
 * @subpackage imap/output
 */
class Hm_Output_filter_message_struct extends Hm_Output_Module {
    /**
     * Build message part section HTML
     */
    protected function output() {
        if ($this->get('msg_struct')) {
            $res = '<table class="msg_parts">';
            if (!$this->get('is_mobile')) {
                $res .= '<colgroup><col class="msg_part_mime"><col class="msg_part_size">';
                $res .= '<col class="msg_part_encoding"><col class="msg_part_charset"><col class="msg_part_desc">';
                $res .= '<col class="msg_part_download"></colgroup>';
            }
            $part = $this->get('imap_msg_part', '1');
            $args = $this->get('msg_download_args', '');
            $res .=  format_msg_part_section($this->get('msg_struct'), $this, $part, $args);
            $res .= '</table>';
            $this->out('msg_parts', $res);
        }
    }
}

/**
 * Format the message headers section of the message view
 * @subpackage imap/output
 */
class Hm_Output_filter_message_headers extends Hm_Output_Module {
    /**
     * Build message header HTML
     */
    protected function output() {
        if ($this->get('msg_headers')) {
            $txt = '';
            $small_headers = array('subject', 'date', 'from', 'to', 'cc', 'flags');
            $reply_args = sprintf('&amp;list_path=imap_%d_%s&amp;uid=%d',
                $this->html_safe($this->get('msg_server_id')),
                $this->html_safe($this->get('msg_folder')),
                $this->html_safe($this->get('msg_text_uid'))
            );
            $msg_part = $this->get('imap_msg_part');
            $headers = $this->get('msg_headers', array());
            if (!array_key_exists('subject', lc_headers($headers)) || !trim(lc_headers($headers)['subject'])) {
                $headers['subject'] = $this->trans('[No Subject]');
            }
            $txt .= '<table class="msg_headers"><colgroup><col class="header_name_col"><col class="header_val_col"></colgroup>';
            foreach ($small_headers as $fld) {
                foreach ($headers as $name => $value) {
                    if ($fld == strtolower($name)) {
                        if ($fld == 'subject') {
                            $txt .= '<tr class="header_'.$fld.'"><th colspan="2">';
                            if (isset($headers['Flags']) && stristr($headers['Flags'], 'flagged')) {
                                $txt .= ' <img alt="" class="account_icon" src="'.Hm_Image_Sources::$star.'" width="16" height="16" /> ';
                            }
                            $txt .= $this->html_safe($value).'</th></tr>';
                        }
                        elseif ($fld == 'date') {
                            $value = sprintf('%s (%s)', strftime('%c %Z', strtotime($value)), human_readable_interval($value));
                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                        }
                        else {
                            if (strtolower($name) == 'flags') {
                                $name = $this->trans('Tags');
                                $value = str_replace('\\', '', $value);
                                $new_value = array();
                                foreach (explode(' ', $value) as $v) {
                                    $new_value[] = $this->trans(trim($v));
                                }
                                $value = implode(', ', $new_value);

                            }
                            $txt .= '<tr class="header_'.$fld.'"><th>'.$this->trans($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                        }
                        break;
                    }
                }
            }
            foreach ($headers as $name => $value) {
                if (!in_array(strtolower($name), $small_headers)) {
                    if (is_array($value)) {
                        foreach ($value as $line) {
                            $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($line).'</td></tr>';
                        }
                    }
                    else {
                        $txt .= '<tr style="display: none;" class="long_header"><th>'.$this->html_safe($name).'</th><td>'.$this->html_safe($value).'</td></tr>';
                    }
                }
            }
            if ($this->get('list_headers')) {
                $txt .= format_list_headers($this);
            }
            $reply_all = false;
            if (array_key_exists('cc', lc_headers($headers))) {
                $reply_all = true;
            }
            $addr = addr_split($headers['To']);
            $size = count($addr);

            $txt .= '<tr><td class="header_space" colspan="2"></td></tr>';
            $txt .= '<tr><th colspan="2" class="header_links">';
            $txt .= '<div class="msg_move_to">'.
                '<a href="#" class="hlink all_headers">'.$this->trans('All headers').'</a>'.
                '<a class="hlink small_headers" style="display: none;" href="#">'.$this->trans('Small headers').'</a>'.
                ' | <a class="reply_link hlink" href="?page=compose&amp;reply=1'.$reply_args.'">'.$this->trans('Reply').'</a>';
                if ($reply_all || $size > 1) {
                    $txt .= ' | <a class="reply_all_link hlink" href="?page=compose&amp;reply_all=1'.$reply_args.'">'.$this->trans('Reply-all').'</a>';
                }
                else {
                    $txt .= ' | <a class="reply_all_link hlink disabled_link">'.$this->trans('Reply-all').'</a>';
                }
                $txt .= ' | <a class="forward_link hlink" href="?page=compose&amp;forward=1'.$reply_args.'">'.$this->trans('Forward').'</a>';
            if ($msg_part === '0') {
                $txt .= ' | <a class="normal_link hlink msg_part_link normal_link" data-message-part="" href="#">'.$this->trans('normal').'</a>';
            }
            else {
                $txt .= ' | <a class="raw_link hlink msg_part_link raw_link" data-message-part="0" href="#">'.$this->trans('raw').'</a>';
            }
            if (isset($headers['Flags']) && stristr($headers['Flags'], 'flagged')) {
                $txt .= ' | <a style="display: none;" class="flagged_link hlink" id="flag_msg" data-state="unflagged" href="#">'.$this->trans('Flag').'</a>';
                $txt .= '<a id="unflag_msg" class="unflagged_link hlink" data-state="flagged" href="#">'.$this->trans('Unflag').'</a>';
            }
            else {
                $txt .= ' | <a id="flag_msg" class="unflagged_link hlink" data-state="unflagged" href="#">'.$this->trans('Flag').'</a>';
                $txt .= '<a style="display: none;" class="flagged_link hlink" id="unflag_msg" data-state="flagged" href="#">'.$this->trans('Unflag').'</a>';
            }
            $txt .= ' | <a class="hlink" id="unread_message" href="#" >'.$this->trans('Unread').'</a>';
            $txt .= ' | <a class="delete_link hlink" id="delete_message" href="#">'.$this->trans('Delete').'</a>';
            $txt .= ' | <a class="hlink" id="copy_message" href="#">'.$this->trans('Copy').'</a>';
            $txt .= ' | <a class="hlink" id="move_message" href="#">'.$this->trans('Move').'</a>';
            $txt .= ' | <a class="archive_link hlink" id="archive_message" href="#">'.$this->trans('Archive').'</a>';
            $txt .= '<div class="move_to_location"></div></div>';
            $txt .= '<input type="hidden" class="move_to_type" value="" />';
            $txt .= '<input type="hidden" class="move_to_string1" value="'.$this->trans('Move to ...').'" />';
            $txt .= '<input type="hidden" class="move_to_string2" value="'.$this->trans('Copy to ...').'" />';
            $txt .= '<input type="hidden" class="move_to_string3" value="'.$this->trans('Removed non-IMAP messages from selection. They cannot be moved or copied').'" />';
            $txt .= '</th></tr>';
            $txt .= '</table>';

            $this->out('msg_headers', $txt, false);
        }
    }
}

/**
 * Format configured IMAP servers for the servers page
 * @subpackage imap/output
 */
class Hm_Output_display_configured_imap_servers extends Hm_Output_Module {
    /**
     * Build HTML for configured IMAP servers
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $res = '';
        foreach ($this->get('imap_servers', array()) as $index => $vals) {

            if (array_key_exists('type', $vals) && $vals['type'] == 'jmap') {
                continue;
            }

            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
            }
            elseif (array_key_exists('nopass', $vals)) {
                if (array_key_exists('user', $vals)) {
                    $user_pc = $vals['user'];
                }
                else {
                    $user_pc = '';
                }
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            $res .= '<div class="configured_server">';
            $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s/%d %s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['port']),
                $vals['tls'] ? 'TLS' : '' );
            $res .= '<form class="imap_connect" method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<input type="hidden" name="imap_server_id" class="imap_server_id" value="'.$this->html_safe($index).'" /><span> '.
                '<label class="screen_reader" for="imap_user_'.$index.'">'.$this->trans('IMAP username').'</label>'.
                '<input '.$disabled.' id="imap_user_'.$index.'" class="credentials" placeholder="'.$this->trans('Username').
                '" type="text" name="imap_user" value="'.$this->html_safe($user_pc).'"></span>'.
                '<span><label class="screen_reader" for="imap_pass_'.$index.'">'.$this->trans('IMAP password').'</label>'.
                '<input '.$disabled.' id="imap_pass_'.$index.'" class="credentials imap_password" placeholder="'.$pass_pc.
                '" type="password" name="imap_pass"></span>';

            if (!isset($vals['user']) || !$vals['user']) {
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete" />';
                $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_imap_connection" />';
            }
            else {
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_imap_connect" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete" />';
                $res .= '<input type="submit" value="'.$this->trans('Forget').'" class="forget_imap_connection" />';
            }
            $hidden = false;
            if (array_key_exists('hide', $vals) && $vals['hide']) {
                $hidden = true;
            }
            $res .= '<input type="submit" ';
            if ($hidden) {
                $res .= 'style="display: none;" ';
            }
            $res .= 'value="'.$this->trans('Hide').'" class="hide_imap_connection" />';
            $res .= '<input type="submit" ';
            if (!$hidden) {
                $res .= 'style="display: none;" ';
            }
            $res .= 'value="'.$this->trans('Unhide').'" class="unhide_imap_connection" />';
            $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
            $res .= '</form></div>';
        }
        $res .= '<br class="clear_float" /></div></div>';
        return $res;
    }
}

/**
 * Format the add IMAP server dialog for the servers page
 * @subpackage imap/output
 */
class Hm_Output_add_imap_server_dialog extends Hm_Output_Module {
    /**
     * Build the HTML for the add server dialog
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $count = count(array_filter($this->get('imap_servers', array()), function($v) { return !array_key_exists('type', $v) || $v['type'] != 'jmap'; }));
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="imap_server_setup"><div data-target=".imap_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            ' '.$this->trans('IMAP Servers').'<div class="server_count">'.$count.'</div></div><div class="imap_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="subtitle">'.$this->trans('Add an IMAP Server').'</div><table>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_name">'.$this->trans('Account name').'</label>'.
            '<input id="new_imap_name" required type="text" name="new_imap_name" class="txt_fld" value="" placeholder="'.$this->trans('Account name').'" /></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_address">'.$this->trans('Server address').'</label>'.
            '<input required type="text" id="new_imap_address" name="new_imap_address" class="txt_fld" placeholder="'.$this->trans('IMAP server address').'" value=""/></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_imap_port">'.$this->trans('IMAP port').'</label>'.
            '<input required type="number" id="new_imap_port" name="new_imap_port" class="port_fld" value="993" placeholder="'.$this->trans('Port').'"></td></tr>'.
            '<tr><td colspan="2"><input type="checkbox" id="new_imap_hidden" name="new_imap_hidden" class="" value="1">'.
            '<label for="new_imap_hidden">'.$this->trans('Hide From Combined Pages').'</label></td></tr>'.
            '<tr><td><input type="radio" name="tls" value="1" id="imap_tls" checked="checked" /> <label for="imap_tls">'.$this->trans('Use TLS').'</label>'.
            '<br /><input type="radio" name="tls" value="0" id="imap_notls" /><label for="imap_notls">'.$this->trans('STARTTLS or unencrypted').'</label></td>'.
            '</tr><tr><td><input type="submit" value="'.$this->trans('Add').'" name="submit_imap_server" /></td></tr>'.
            '</table></form>';
    }
}

/**
 * Format the add IMAP server dialog for the servers page
 * @subpackage imap/output
 */
class Hm_Output_add_jmap_server_dialog extends Hm_Output_Module {
    /**
     * Build the HTML for the add server dialog
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $count = count(array_filter($this->get('imap_servers', array()), function($v) { return array_key_exists('type', $v) && $v['type'] == 'jmap';}));
        $count = sprintf($this->trans('%d configured'), $count);
        return '<div class="jmap_server_setup"><div data-target=".jmap_section" class="server_section">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            ' '.$this->trans('JMAP Servers').'<div class="server_count">'.$count.'</div></div><div class="jmap_section"><form class="add_server" method="POST">'.
            '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
            '<div class="subtitle">'.$this->trans('Add a JMAP Server').'</div><table>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_jmap_name">'.$this->trans('Account name').'</label>'.
            '<input id="new_jmap_name" required type="text" name="new_jmap_name" class="txt_fld" value="" placeholder="'.$this->trans('Account name').'" /></td></tr>'.
            '<tr><td colspan="2"><label class="screen_reader" for="new_jmap_address">'.$this->trans('Server URL').'</label>'.
            '<input required type="url" id="new_jmap_address" name="new_jmap_address" class="txt_fld" placeholder="'.$this->trans('Server URL').'" value=""/></td></tr>'.
            '<tr><td colspan="2"><input type="checkbox" id="new_jmap_hidden" name="new_jmap_hidden" class="" value="1">'.
            '<label for="new_jmap_hidden">'.$this->trans('Hide From Combined Pages').'</label></td></tr>'.
            '</tr><tr><td><input type="submit" value="'.$this->trans('Add').'" name="submit_jmap_server" /></td></tr>'.
            '</table></form>';
    }
}

/**
 * Format configured JMAP servers for the servers page
 * @subpackage imap/output
 */
class Hm_Output_display_configured_jmap_servers extends Hm_Output_Module {
    /**
     * Build HTML for configured JMAP servers
     */
    protected function output() {
        if ($this->get('single_server_mode')) {
            return '';
        }
        $res = '';
        foreach ($this->get('imap_servers', array()) as $index => $vals) {

            if (!array_key_exists('type', $vals) || $vals['type'] != 'jmap') {
                continue;
            }
            if (array_key_exists('user', $vals) && !array_key_exists('nopass', $vals)) {
                $disabled = 'disabled="disabled"';
                $user_pc = $vals['user'];
                $pass_pc = $this->trans('[saved]');
            }
            elseif (array_key_exists('nopass', $vals)) {
                if (array_key_exists('user', $vals)) {
                    $user_pc = $vals['user'];
                }
                else {
                    $user_pc = '';
                }
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            else {
                $user_pc = '';
                $pass_pc = $this->trans('Password');
                $disabled = '';
            }
            $res .= '<div class="configured_server">';
            $res .= sprintf('<div class="server_title">%s</div><div class="server_subtitle">%s</div>',
                $this->html_safe($vals['name']), $this->html_safe($vals['server']));
            $res .= '<form class="imap_connect" method="POST">'.
                '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />'.
                '<input type="hidden" name="imap_server_id" class="imap_server_id" value="'.$this->html_safe($index).'" /><span> '.
                '<label class="screen_reader" for="imap_user_'.$index.'">'.$this->trans('JMAP username').'</label>'.
                '<input '.$disabled.' id="imap_user_'.$index.'" class="credentials" placeholder="'.$this->trans('Username').
                '" type="text" name="imap_user" value="'.$this->html_safe($user_pc).'"></span>'.
                '<span><label class="screen_reader" for="imap_pass_'.$index.'">'.$this->trans('JMAP password').'</label>'.
                '<input '.$disabled.' id="imap_pass_'.$index.'" class="credentials imap_password" placeholder="'.$pass_pc.
                '" type="password" name="imap_pass"></span>';

            if (!isset($vals['user']) || !$vals['user']) {
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete" />';
                $res .= '<input type="submit" value="'.$this->trans('Save').'" class="save_imap_connection" />';
            }
            else {
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_imap_connect" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="imap_delete" />';
                $res .= '<input type="submit" value="'.$this->trans('Forget').'" class="forget_imap_connection" />';
            }
            $hidden = false;
            if (array_key_exists('hide', $vals) && $vals['hide']) {
                $hidden = true;
            }
            $res .= '<input type="submit" ';
            if ($hidden) {
                $res .= 'style="display: none;" ';
            }
            $res .= 'value="'.$this->trans('Hide').'" class="hide_imap_connection" />';
            $res .= '<input type="submit" ';
            if (!$hidden) {
                $res .= 'style="display: none;" ';
            }
            $res .= 'value="'.$this->trans('Unhide').'" class="unhide_imap_connection" />';
            $res .= '<input type="hidden" value="ajax_imap_debug" name="hm_ajax_hook" />';
            $res .= '</form></div>';
        }
        $res .= '<br class="clear_float" /></div></div>';
        return $res;
    }
}
/**
 * Format the IMAP status output on the info page
 * @subpackage imap/output
 */
class Hm_Output_display_imap_status extends Hm_Output_Module {
    /**
     * Build the HTML for the status rows. Will be populated by an ajax call per server
     */
    protected function output() {
        $res = '';
        foreach ($this->get('imap_servers', array()) as $index => $vals) {
            $res .= '<tr><td>IMAP</td><td>'.$vals['name'].'</td><td class="imap_status_'.$index.'"></td>'.
                '<td class="imap_detail_'.$index.'"></td></tr>';
        }
        return $res;
    }
}

/**
 * Output a hidden field with all the IMAP server ids
 * @subpackage imap/output
 */
class Hm_Output_imap_server_ids extends Hm_Output_Module {
    /**
     * Build HTML for the IMAP server ids
     */
    protected function output() {
        return '<input type="hidden" class="imap_server_ids" value="'.$this->html_safe(implode(',', array_keys($this->get('imap_servers', array ())))).'" />';
    }
}

/**
 * Format a list of subfolders
 * @subpackage imap/output
 */
class Hm_Output_filter_expanded_folder_data extends Hm_Output_Module {
    /**
     * Build the HTML for a list of subfolders. The page cache is used to pass this to the folder list.
     */
    protected function output() {
        $res = '';
        $folder_data = $this->get('imap_expanded_folder_data', array());
        if (!empty($folder_data)) {
            $res .= format_imap_folder_section($folder_data, $this->get('imap_expanded_folder_id'), $this);
            $this->out('imap_expanded_folder_formatted', $res);
        }
    }
}

/**
 * Add move/copy dialog to the message list controls
 * @subpackage imap/output
 */
class Hm_Output_move_copy_controls extends Hm_Output_Module {
    protected function output() {
        if ($this->get('move_copy_controls', false)) {
            $res = '<span class="ctr_divider"></span> <a class="imap_move disabled_input" href="#" data-action="copy">'.$this->trans('Copy').'</a>';
            $res .= '<a class="imap_move disabled_input" href="#" data-action="move">'.$this->trans('Move').'</a>';
            $res .= '<div class="move_to_location"></div>';
            $res .= '<input type="hidden" class="move_to_type" value="" />';
            $res .= '<input type="hidden" class="move_to_string1" value="'.$this->trans('Move to ...').'" />';
            $res .= '<input type="hidden" class="move_to_string2" value="'.$this->trans('Copy to ...').'" />';
            $res .= '<input type="hidden" class="move_to_string3" value="'.$this->trans('Removed non-IMAP messages from selection. They cannot be moved or copied').'" />';
            $this->concat('msg_controls_extra', $res);
        }
    }
}

/**
 * Format the status of an IMAP connection used on the info page
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_status_data extends Hm_Output_Module {
    /**
     * Build AJAX response for an IMAP server status
     */
    protected function output() {
        $res = '';
        if ($this->get('imap_connect_status') != 'disconnected') {
            $res .= '<span class="online">'.$this->trans(ucwords($this->get('imap_connect_status'))).
                '</span> in '.round($this->get('imap_connect_time', 0.0), 3);
        }
        else {
            $res .= '<span class="down">'.$this->trans('Down').'</span>';
        }
        $this->out('imap_status_display', $res);
    }
}

/**
 * Format the top level IMAP folders for the folder list
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_folders extends Hm_Output_Module {
    /**
     * Build HTML for the Email section of the folder list
     */
    protected function output() {
        $res = '';
        if ($this->get('imap_folders')) {
            foreach ($this->get('imap_folders', array()) as $id => $folder) {
                $res .= '<li class="imap_'.intval($id).'_"><a href="#" class="imap_folder_link" data-target="imap_'.intval($id).'_">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<img class="account_icon" alt="'.$this->trans('Toggle folder').'" src="'.Hm_Image_Sources::$folder.'" width="16" height="16" /> ';
                }
                $res .= $this->html_safe($folder).'</a></li>';
            }
        }
        if ($res) {
            $this->append('folder_sources', array('email_folders', $res));
        }
        return '';
    }
}

/**
 * Format search results row
 * @subpackage imap/output
 */
class Hm_Output_filter_imap_search extends Hm_Output_Module {
    /**
     * Build ajax response from an IMAP server for a search
     */
    protected function output() {
        if ($this->get('imap_search_results')) {
            prepare_imap_message_list($this->get('imap_search_results'), $this, 'search');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the Flagged page
 * @subpackage imap/output
 */
class Hm_Output_filter_flagged_data extends Hm_Output_Module {
    /**
     * Build ajax response for the Flagged message list
     */
    protected function output() {
        if ($this->get('imap_flagged_data')) {
            prepare_imap_message_list($this->get('imap_flagged_data'), $this, 'flagged');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the Unread page
 * @subpackage imap/output
 */
class Hm_Output_filter_unread_data extends Hm_Output_Module {
    /**
     * Build ajax response for the Unread message list
     */
    protected function output() {
        if ($this->get('imap_unread_data')) {
            prepare_imap_message_list($this->get('imap_unread_data'), $this, 'unread');
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the Sent E-mail page
 * @subpackage imap/output
 */
class Hm_Output_filter_sent_data extends Hm_Output_Module {
    /**
     * Build ajax response for the All E-mail message list
     */
    protected function output() {
        if ($this->get('imap_sent_data')) {
            prepare_imap_message_list($this->get('imap_sent_data'), $this, 'sent');
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the All E-mail page
 * @subpackage imap/output
 */
class Hm_Output_filter_all_email extends Hm_Output_Module {
    /**
     * Build ajax response for the All E-mail message list
     */
    protected function output() {
        if ($this->get('imap_combined_inbox_data')) {
            prepare_imap_message_list($this->get('imap_combined_inbox_data'), $this, 'email');
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Format message headers for the Everthing page
 * @subpackage imap/output
 */
class Hm_Output_filter_combined_inbox extends Hm_Output_Module {
    /**
     * Build ajax response for the Everthing message list
     */
    protected function output() {
        if ($this->get('imap_combined_inbox_data')) {
            prepare_imap_message_list($this->get('imap_combined_inbox_data'), $this, 'combined_inbox');
        }
        else {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Normal IMAP folder view
 * @subpackage imap/output
 */
class Hm_Output_filter_folder_page extends Hm_Output_Module {
    /**
     * Build ajax response for a folder page
     */
    protected function output() {
        $res = array();
        if ($this->get('imap_mailbox_page')) {
            prepare_imap_message_list($this->get('imap_mailbox_page'), $this, false);
            $details = $this->get('imap_folder_detail');
            if ($details['offset'] == 0) {
                $page_num = 1;
            }
            else {
                $page_num = ($details['offset']/$details['limit']) + 1;
            }
            $this->out('page_links', build_page_links($details['limit'], $page_num, $details['detail']['exists'],
                $this->get('imap_mailbox_page_path'), $this->html_safe($this->get('list_filter')), $this->html_safe($this->get('list_sort'))));
        }
        elseif (!$this->get('formatted_message_list')) {
            $this->out('formatted_message_list', array());
        }
    }
}

/**
 * Start the sent section on the settings page.
 * @subpackage imap/output
 */
class Hm_Output_start_sent_settings extends Hm_Output_Module {
    /**
     * Settings in this section control the Sent E-mail view.
     */
    protected function output() {
        return '<tr><td data-target=".sent_setting" colspan="2" class="settings_subtitle">'.
            '<img alt="" src="'.Hm_Image_Sources::$env_closed.'" width="16" height="16" />'.
            $this->trans('Sent').'</td></tr>';
    }
}

/**
 * Option for the "received since" date range for the All E-mail page
 * @subpackage imap/output
 */
class Hm_Output_sent_since_setting extends Hm_Output_Module {
    protected function output() {
        if (!email_is_active($this->get('router_module_list'))) {
            return '';
        }
        $since = DEFAULT_SINCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('sent_since', $settings) && $settings['sent_since']) {
            $since = $settings['sent_since'];
        }
        return '<tr class="sent_setting"><td><label for="sent_since">'.
            $this->trans('Show messages received since').'</label></td>'.
            '<td>'.message_since_dropdown($since, 'sent_since', $this).'</td></tr>';
    }
}

/**
 * Option to unflag a message on reply
 * @subpackage imap/output
 */
class Hm_Output_imap_unflag_on_send_controls extends Hm_Output_Module {
    protected function output() {
        $flagged = false;
        $details = $this->get('reply_details', array());
        if (is_array($details) && array_key_exists('msg_headers', $details) && array_key_exists('Flags', $details['msg_headers'])) {
            if (stristr($details['msg_headers']['Flags'], 'flagged')) {
                $flagged = true;
            }
        }
        if (!$flagged) {
            return;
        }
        if ($this->get('list_path') || $this->get('compose_msg_path')) {
            return '<div class="unflag_send_div"><input type="checkbox" value="1" name="compose_unflag_send" id="unflag_send">'.
                '<label for="unflag_send">'.$this->trans('Unflag on reply').'</label></div>';
        }
    }
}
/**
 * Option to enable/disable simple message structure on the message view
 * @subpackage imap/output
 */
class Hm_Output_imap_simple_msg_parts extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        if (array_key_exists('simple_msg_parts', $settings) && $settings['simple_msg_parts']) {
            $checked = ' checked="checked"';
        }
        return '<tr class="general_setting"><td><label for="simple_msg_parts">'.
            $this->trans('Show simple message part structure when reading a message').'</label></td>'.
            '<td><input type="checkbox" '.$checked.' id="simple_msg_parts" name="simple_msg_parts" value="1" /></td></tr>';
    }
}

/**
 * Output imap prefetch ids
 * @subpackage imap/output
 */
class Hm_Output_prefetch_imap_folder_ids extends Hm_Output_Module {
    protected function output() {
        $ids = $this->get('prefetch_folder_ids', array());
        if (count($ids) == 0) {
            return;
        }
        return '<input type="hidden" id="imap_prefetch_ids" value="'.$this->html_safe(implode(',', $ids)).'" />';
    }
}

/**
 * Option to set the per page count for IMAP folder views
 * @subpackage imap/output
 */
class Hm_Output_imap_per_page_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings', array());
        $per_page = 20;
        if (array_key_exists('imap_per_page', $settings)) {
            $per_page = $settings['imap_per_page'];
        }
        return '<tr class="general_setting"><td><label for="imap_per_page">'.
            $this->trans('Messages per page for IMAP folder views').'</label></td><td><input type="text" id="imap_per_page" '.
            'name="imap_per_page" value="'.$this->html_safe($per_page).'" /></td></tr>';
    }
}

/**
 * Option to enable/disable message part icons on the message view
 * @subpackage imap/output
 */
class Hm_Output_imap_msg_icons_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        if (array_key_exists('msg_part_icons', $settings) && $settings['msg_part_icons']) {
            $checked = ' checked="checked"';
        }
        return '<tr class="general_setting"><td><label for="msg_part_icons">'.
            $this->trans('Show message part icons when reading a message').'</label></td>'.
            '<td><input type="checkbox" '.$checked.' id="msg_part_icons" name="msg_part_icons" value="1" /></td></tr>';
    }
}

/**
 * Option to limit mail fromat to text only when possible (not defaulting to HTML)
 * @subpackage imap/output
 */
class Hm_Output_text_only_setting extends Hm_Output_Module {
    protected function output() {
        $checked = '';
        $settings = $this->get('user_settings', array());
        if (array_key_exists('text_only', $settings) && $settings['text_only']) {
            $checked = ' checked="checked"';
        }
        return '<tr class="general_setting"><td><label for="text_only">'.
            $this->trans('Prefer text over HTML when reading messages').'</label></td>'.
            '<td><input type="checkbox" '.$checked.' id="text_only" name="text_only" value="1" /></td></tr>';
    }
}

/**
 * Option for the maximum number of messages per source for the All E-mail  page
 * @subpackage imap/output
 */
class Hm_Output_sent_source_max_setting extends Hm_Output_Module {
    protected function output() {
        $sources = DEFAULT_PER_SOURCE;
        $settings = $this->get('user_settings', array());
        if (array_key_exists('sent_per_source', $settings)) {
            $sources = $settings['sent_per_source'];
        }
        return '<tr class="sent_setting"><td><label for="sent_per_source">'.
            $this->trans('Max messages per source').'</label></td>'.
            '<td><input type="text" size="2" id="sent_per_source" name="sent_per_source" value="'.$this->html_safe($sources).'" /></td></tr>';
    }
}

