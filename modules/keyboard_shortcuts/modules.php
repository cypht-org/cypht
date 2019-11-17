<?php

/**
 * keyboard shortcuts modules
 * @package modules
 * @subpackage keyboard_shortcuts
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_load_keyboard_shortcuts extends Hm_Handler_Module {
    public function process() {
        $this->out('keyboard_shortcut_data', shortcut_defaults($this->user_config));
        $this->out('shortcuts_enabled', $this->user_config->get('enable_keyboard_shortcuts_setting', false));
    }
}

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_get_shortcut_setting extends Hm_Handler_Module {
    public function process() {
        $this->out('shortcuts_enabled', $this->user_config->get('enable_keyboard_shortcuts_setting', false));
    }
}

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_process_edit_shortcut extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('shortcut_meta', 'shortcut_key', 'shortcut_id'));
        if ($success) {
            $shortcuts = $this->get('keyboard_shortcut_data');
            $codes = keycodes();
            if (!array_key_exists($form['shortcut_id'], $shortcuts)) {
                Hm_Msgs::add('ERRUnknown shortcut');
                return;
            }
            if (!array_search($form['shortcut_key'], $codes)) {
                Hm_Msgs::add('ERRUnknown shortcut key');
                return;
            }
            $meta_list = array();
            foreach ($form['shortcut_meta'] as $meta) {
                if (!in_array($meta, array('meta', 'alt', 'shift', 'control', 'none'))) {
                    Hm_Msgs::add('ERRUknown modifier key');
                    return;
                }
                if ($meta != 'none') {
                    $meta_list[] = $meta;
                }
            }
            $custom_shortcuts = $this->user_config->get('keyboard_shortcuts', array());
            $custom_shortcuts[$form['shortcut_id']] = array('meta' => $meta_list, 'key' => $form['shortcut_key']);
            $this->user_config->set('keyboard_shortcuts', $custom_shortcuts);
            $user_data = $this->user_config->dump();
            $this->session->set('user_data', $user_data);
            $this->session->record_unsaved('Shortcut updated');
            Hm_Msgs::add('Shortcut updated');
        }
    }
}

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_load_edit_id extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('edit_id', $this->request->get)) {
            $shortcuts = $this->get('keyboard_shortcut_data');
            if (array_key_exists($this->request->get['edit_id'], $shortcuts)) {
                $details = $shortcuts[$this->request->get['edit_id']];
                $details['id'] = $this->request->get['edit_id'];
                $this->out('shortcut_details', $details);
            }
        }
    }
}

/**
 * @subpackage keyboard_shortcuts/handler
 */
class Hm_Handler_process_enable_shortcut_setting extends Hm_Handler_Module {
    public function process() {
        function shortcut_enabled_callback($val) { return $val; }
        process_site_setting('enable_keyboard_shortcuts', $this, 'shortcut_enabled_callback', false, true);
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_enable_shortcut_setting extends Hm_Output_Module {
    protected function output() {
        $settings = $this->get('user_settings');
        if (array_key_exists('enable_keyboard_shortcuts', $settings) && $settings['enable_keyboard_shortcuts']) {
            $checked = ' checked="checked"';
        }
        else {
            $checked = '';
        }
        return '<tr class="general_setting"><td><label for="enable_keyboard_shortcuts">'.
            $this->trans('Enable keyboard shortcuts').'</label></td>'.
            '<td><input type="checkbox" '.$checked.
            ' value="1" id="enable_keyboard_shortcuts" name="enable_keyboard_shortcuts" /></td></tr>';
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_start_shortcuts_page extends Hm_Output_Module {
    protected function output() {
        return '<div class="shortcut_content"><div class="content_title">'.$this->trans('Shortcuts').'</div>';
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_shortcut_edit_form extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('shortcut_details');
        if (!$details || !is_array($details)) {
            return;
        }
        $codes = keycodes();
        $meta = array('none', 'shift', 'control', 'alt', 'meta');
        $res = '<div class="settings_subtitle">'.$this->trans('Edit Shortcut').'</div>';
        $res .= '<div class="edit_shortcut_form"><form method="POST" action="?page=shortcuts&edit_id='.$this->html_safe($details['id']).'">';
        $res .= '<input type="hidden" name="shortcut_id" value="'.$this->html_safe($details['id']).'" />';
        $res .= '<input type="hidden" name="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />';
        $res .= '<table>';
        $res .= '<tr><th colspan="2">'.$this->trans(ucfirst($details['group'])).' : '.
            $this->trans($details['label']).'</th></tr>';
        $res .= '<tr><td>'.$this->trans('Modifier Key(s)').'</td>';
        $res .= '<td><select required multiple size="5" name="shortcut_meta[]">';
        foreach ($meta as $v) {
            $res .= '<option ';
            if (in_array($v, $details['control_chars'], true)) {
                $res .= 'selected="selected" ';
            }
            elseif (count($details['control_chars']) == 0 && $v == 'none') {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$v.'">'.ucfirst($v).'</option>';
        }
        $res .= '</select></td></tr>';
        $res .= '<tr><td>'.$this->trans('Character').'</td>';
        $res .= '<td><select required " name="shortcut_key">';
        foreach ($codes as $name => $val) {
            $res .= '<option ';
            if ($val == $details['char']) {
                $res .= 'selected="selected" ';
            }
            $res .= 'value="'.$this->html_safe($val).'">'.$this->html_safe($name).'</option>';
        }
        $res .= '</select></td></tr>';
        $res .= '<tr><td colspan="2"><input type="submit" value="'.
            $this->trans('Update').'" /> <input type="button" value="'.
            $this->trans('Cancel').'" class="reset_shortcut" /></td></tr>';
        $res .= '</table></form></div>';
        return $res;
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_shortcuts_content extends Hm_Output_Module {
    protected function output() {
        $shortcuts = $this->get('keyboard_shortcut_data');
        $res = '<table class="shortcut_table">';
        $res .= '<tr><td colspan="3" class="settings_subtitle">'.$this->trans('General').'</th></tr>';
        $res .= format_shortcut_section($shortcuts, 'general', $this);
        $res .= '<tr><td colspan="3" class="settings_subtitle">'.$this->trans('Message List').'</td></tr>';
        $res .= format_shortcut_section($shortcuts, 'message_list', $this);
        $res .= '<tr><td colspan="3" class="settings_subtitle">'.$this->trans('Message View').'</td></tr>';
        $res .= format_shortcut_section($shortcuts, 'message', $this);
        $res .= '</table>';
        $res .= '</div>';
        return $res;
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_keyboard_shortcut_data extends Hm_Output_Module {
    protected function output() {
        if ($this->get('shortcuts_enabled')) {
            return '<script type="text/javascript">'.format_shortcuts($this->get('keyboard_shortcut_data')).'</script>';
        }
    }
}

/**
 * @subpackage keyboard_shortcuts/output
 */
class Hm_Output_shortcuts_page_link extends Hm_Output_Module {
    protected function output() {
        if ($this->get('shortcuts_enabled')) {
            $res = '<li class="menu_shortcuts"><a class="unread_link" href="?page=shortcuts">';
            if (!$this->get('hide_folder_icons')) {
                $res .= '<img class="account_icon" src="'.$this->html_safe(Hm_Image_Sources::$code).'" alt="" width="16" height="16" /> ';
            }
            $res .= $this->trans('Shortcuts').'</a></li>';
            if ($this->format == 'HTML5') {
                return $res;
            }
            $this->concat('formatted_folder_list', $res);
        }
    }
}

/**
 * @subpackage keyboard_shortcuts/functions
 */
if (!hm_exists('format_shortcut_section')) {
function format_shortcut_section($data, $type, $output_mod) {
    $res = '';
    $codes = keycodes();
    foreach ($data as $index => $vals) {
        if ($vals['group'] == $type) {
            $c_keys = ucfirst(implode(' + ', $vals['control_chars']));
            if ($c_keys) {
                $c_keys .= ' +';
            }
            $char = array_search($vals['char'], $codes);
            $res .= sprintf('<tr><th class="keys">%s %s</th><th>%s</th>'.
                '<td><a href="?page=shortcuts&edit_id=%s"><img width="16" height="16" alt="'.
                $output_mod->trans('Update').'" class="kbd_config" src="%s" /><a></td></tr>',
                $output_mod->html_safe($c_keys), $output_mod->html_safe($char),
                $output_mod->trans($vals['label']), $index, Hm_Image_Sources::$cog);
        }
    }
    return $res;
}}

/**
 * @subpackage keyboard_shortcuts/functions
 */
if (!hm_exists('shortcut_defaults')) {
function shortcut_defaults($user_config=false) {
    $res = array(
        array('label' => 'Unfocus all input elements', 'group' => 'general', 'page' => '*', 'control_chars' => array(), 'char' => 27, 'action' => 'Keyboard_Shortcuts.unfocus', 'target' => 'false'),
        array('label' => 'Jump to the "Everything" page', 'group' => 'general', 'page' => '*', 'control_chars' => array('control', 'shift'), 'char' => 69, 'action' => 'ks_redirect', 'target' => '?page=message_list&list_path=combined_inbox'),
        array('label' => 'Jump to the "Unread" page', 'group' => 'general', 'page' => '*', 'control_chars' => array('control', 'shift'), 'char' => 85, 'action' => 'ks_redirect', 'target' => '?page=message_list&list_path=unread'),
        array('label' => 'Jump to the "Flagged" page', 'group' => 'general', 'page' => '*', 'control_chars' => array('control', 'shift'), 'char' => 70, 'action' => 'ks_redirect', 'target' => '?page=message_list&list_path=flagged'),
        array('label' => 'Jump to History', 'group' => 'general', 'page' => '*', 'control_chars' => array('control', 'shift'), 'char' => 72, 'action' => 'ks_redirect', 'target' => '?page=history'),
        array('label' => 'Jump to Contacts', 'group' => 'general', 'page' => '*', 'control_chars' => array('control', 'shift'), 'char' => 67, 'action' => 'ks_redirect', 'target' => '?page=contacts'),
        array('label' => 'Jump to the Compose page', 'group' => 'general', 'page' => '*', 'control_chars' => array('control', 'shift'), 'char' => 83, 'action' => 'ks_redirect', 'target' => '?page=compose'),
        array('label' => 'Toggle the folder list', 'group' => 'general', 'page' => '*', 'control_chars' => array('control', 'shift'), 'char' => 89, 'action' => 'Hm_Folders.toggle_folder_list', 'target' => false),

        array('label' => 'Focus the next message in the list', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array(), 'char' => 78, 'action' => 'ks_next_msg_list', 'target' => false),
        array('label' => 'Focus the previous message in the list', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array(), 'char' => 80, 'action' => 'ks_prev_msg_list', 'target' => false),
        array('label' => 'Open the currently focused message', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array(), 'char' => 13, 'action' => 'ks_load_msg', 'target' => false),
        array('label' => 'Select/unselect the currently focused message', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array(), 'char' => 83, 'action' => 'ks_select_msg', 'target' => false),
        array('label' => 'Toggle all message selections', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array(), 'char' => 65, 'action' => 'ks_select_all', 'target' => false),
        array('label' => 'Mark selected messages as read', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array('shift'), 'char' => 82, 'action' => 'ks_click_button', 'target' => '.msg_read'),
        array('label' => 'Mark selected messages as unread', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array('shift'), 'char' => 85, 'action' => 'ks_click_button', 'target' => '.msg_unread'),
        array('label' => 'Mark selected messages as flagged', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array('shift'), 'char' => 70, 'action' => 'ks_click_button', 'target' => '.msg_flag'),
        array('label' => 'Mark selected messages as unflagged', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array('shift'), 'char' => 69, 'action' => 'ks_click_button', 'target' => '.msg_unflag'),
        array('label' => 'Delete selected messages', 'group' => 'message_list', 'page' => 'message_list', 'control_chars' => array('shift'), 'char' => 68, 'action' => 'ks_click_button', 'target' => '.msg_delete'),

        array('label' => 'View the next message in the list', 'group' => 'message', 'page' => 'message', 'control_chars' => array(), 'char' => 78, 'action' =>  'ks_follow_link', 'target' =>  '.nlink'),
        array('label' => 'View the previous message in the list', 'group' => 'message', 'page' => 'message', 'control_chars' => array(), 'char' => 80, 'action' => 'ks_follow_link', 'target' => '.plink'),
        array('label' => 'Reply', 'group' => 'message', 'page' => 'message', 'control_chars' => array(), 'char' =>  82, 'action' =>  'ks_follow_link', 'target' =>  '.reply_link'),
        array('label' => 'Reply-all', 'group' => 'message', 'page' => 'message', 'control_chars' => array('shift'), 'char' =>  82, 'action' =>  'ks_follow_link', 'target' =>  '.reply_all_link'),
        array('label' => 'Forward', 'group' => 'message', 'page' => 'message', 'control_chars' => array('shift'), 'char' =>  70, 'action' =>  'ks_follow_link', 'target' =>  '.forward_link'),
        array('label' => 'Flag the message', 'group' => 'message', 'page' => 'message', 'control_chars' => array(), 'char' =>  70, 'action' =>  'ks_click_button', 'target' =>  '.flagged_link'),
        array('label' => 'Unflag the message', 'group' => 'message', 'page' => 'message', 'control_chars' => array(), 'char' =>  85, 'action' =>  'ks_click_button', 'target' =>  '.unflagged_link'),
        array('label' => 'Delete the message', 'group' => 'message', 'page' => 'message', 'control_chars' => array(), 'char' =>  68, 'action' =>  'ks_click_button', 'target' =>  '.delete_link'),
    );
    if (!$user_config) {
        return $res;
    }
    $shortcuts = $user_config->get('keyboard_shortcuts', array());
    foreach ($shortcuts as $index => $vals) {
        $res[$index]['char'] = $vals['key'];
        $res[$index]['control_chars'] = $vals['meta'];
    }
    return $res;
}}

/**
 * @subpackage keyboard_shortcuts/functions
 */
if (!hm_exists('fromat_keyboard_action')) {
function format_keyboard_action($action) {
    $actions = Array(
        'Keyboard_Shortcuts.unfocus' => 'unfocus',
        'Hm_Folders.toggle_folder_list' => 'toggle',
        'ks_redirect' => 'redirect',
        'ks_next_msg_list' => 'next',
        'ks_prev_msg_list' => 'prev',
        'ks_load_msg' => 'load',
        'ks_select_msg' => 'select',
        'ks_select_all' => 'select_all',
        'ks_click_button' => 'click',
        'ks_follow_link' => 'link'
    );
    return $actions[$action];
}}

/**
 * @subpackage keyboard_shortcuts/functions
 */
if (!hm_exists('format_shortcuts')) {
function format_shortcuts($data) {
    $res = "var shortcuts = [\n";
    foreach ($data as $vals) {
        $c_chars = implode(',', array_map(function($v) { return "'".$v."'"; }, $vals['control_chars']));
        $res .= sprintf("{'page': '%s', 'control_chars': [%s], 'char': %s, 'action': '%s', 'target': '%s'},\n",
            $vals['page'], $c_chars, $vals['char'], format_keyboard_action($vals['action']), $vals['target']);
    }
    $res .= "];\n";
    return $res;
}}

/**
 * @subpackage keyboard_shortcuts/functions
 */
if (!hm_exists('keycodes')) {
function keycodes() {
	return array(
		'backspace' => 8, 'enter' => 13, 'pause/break' => 19, 'escape' => 27, 'page up' => 33, 'page down' => 34,
		'end' => 35, 'home' => 36, 'left arrow' => 37, 'up arrow' => 38, 'right arrow' => 39, 'down arrow' => 40, 'insert' => 45, 'delete' => 46,
		'0' => 48, '1' => 49, '2' => 50, '3' => 51, '4' => 52, '5' => 53, '6' => 54, '7' => 55, '8' => 56, '9' => 57, 'a' => 65, 'b' => 66, 'c' => 67,
		'd' => 68, 'e' => 69, 'f' => 70, 'g' => 71, 'h' => 72, 'i' => 73, 'j' => 74, 'k' => 75, 'l' => 76, 'm' => 77, 'n' => 78, 'o' => 79, 'p' => 80,
		'q' => 81, 'r' => 82, 's' => 83, 't' => 84, 'u' => 85, 'v' => 86, 'w' => 87, 'x' => 88, 'y' => 89, 'z' => 90, 'numpad 0' => 96, 'numpad 1' => 97,
		'numpad 2' => 98, 'numpad 3' => 99, 'numpad 4' => 100, 'numpad 5' => 101, 'numpad 6' => 102, 'numpad 7' => 103, 'numpad 8' => 104, 'numpad 9' => 105,
		'multiply' => 106, 'add' => 107, 'subtract' => 109, 'decimal point' => 110, 'divide' => 111, 'f1' => 112, 'f2' => 113, 'f3' => 114, 'f4' => 115,
		'f5' => 116, 'f6' => 117, 'f7' => 118, 'f8' => 119, 'f9' => 120, 'f10' => 121, 'f11' => 122, 'f12' => 123,
		'semi-colon' => 186, 'equal sign' => 187, 'comma' => 188, 'dash' => 189, 'period' => 190, 'forward slash' => 191, 'grave accent' => 192,
		'open bracket' => 219, 'back slash' => 220, 'close bracket' => 221, 'single quote' => 222
	);
}}

