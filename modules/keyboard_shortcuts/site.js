'use strict';

var ks_follow_link = function(target) {
    var link = $(target);
    if (link.length > 0) {
        document.location.href = link.attr('href');
    }
};

var ks_redirect = function(target) {
    document.location.href = target;
};

var ks_select_all = function() {
    Hm_Message_List.toggle_rows();
};

var ks_select_msg = function() {
    var focused = $(document.activeElement);
    $('input', focused).each(function() {
        if ($(this).prop('checked')) {
            $(this).prop('checked', false);
        }
        else {
            $(this).prop('checked', true);
        }
    });
    Hm_Message_List.toggle_msg_controls();
};

var ks_prev_msg_list = function() {
    var focused = $(document.activeElement);
    if (focused.prop('tagName').toLowerCase() != 'tr') {
        var row = $('.message_table tbody tr').last();
        row.focus();
    }
    else {
        focused.prev().focus();
    }
};

var ks_load_msg = function() {
    var focused = $(document.activeElement);
    var inline;
    if (focused.prop('tagName').toLowerCase() == 'tr') {
        try {
            inline = inline_msg();
        }
        catch (e) {
            inline = false;
        }
        if (inline) {
            $('a', focused).trigger('click');
        }
        else {
            document.location.href = $('a', focused).attr('href');
        }
    }
};

var ks_next_msg_list = function() {
    var focused = $(document.activeElement);
    if (focused.prop('tagName').toLowerCase() != 'tr') {
        var row = $('.message_table tbody tr').first();
        row.focus();
    }
    else {
        focused.next().focus();
    }
};

var ks_click_button = function(target) {
    $(target).trigger('click');
};

var Keyboard_Shortcuts = {

    unfocus: function() {
        $('input').blur();
        $('textarea').blur();
    },

    check: function(e, shortcuts) {
        var combo;
        var index;
        var matched;
        var control_keys = {'alt': e.altKey, 'shift': e.shiftKey, 'meta': e.metaKey, 'ctrl': e.ctrlKey};
        if (e.keyCode == 27) {
            Keyboard_Shortcuts.unfocus()
            return false;
        }
        if (Keyboard_Shortcuts.in_input_tag(e)) {
            return true;
        }
        for (index in shortcuts) {
            combo = shortcuts[index];
            if (combo['page'] != '*' && combo['page'] != hm_page_name()) {
                continue;
            }
            if (e.keyCode != combo['char']) {
                continue;
            }
            matched = Keyboard_Shortcuts.check_control_chars(combo['control_chars'], control_keys);
            if (matched) {
                combo['action'](combo['target']);
                return false;
            }
        }
        return true;
    },

    check_control_char: function(key_type, control_chars, matched, key_status) {
        if (matched && $.inArray(key_type, control_chars) !== -1 && !key_status) {
            matched = false;
        }
        else if ($.inArray(key_type, control_chars) === -1  && key_status) {
            matched = false;
        }
        return matched
    },

    in_input_tag: function(e) {
        var tag = e.target.tagName.toLowerCase();
        if (tag == 'input' || tag == 'textarea') {
            return true;
        }
        return false;
    },

    check_control_chars: function(control_chars, control_keys) {
        var key_type;
        var key_status;
        var matched = true;
        for (key_type in control_keys) {
            key_status = control_keys[key_type];
            matched = Keyboard_Shortcuts.check_control_char(key_type, control_chars, matched, key_status);
        }
        return matched;
    }
};

$(function() {
    if ($('.menu_shortcuts').length) {
        var shortcuts = [
            {'page': '*', 'control_chars': ['meta'], 'char': 69, 'action': ks_redirect, 'target': '?page=message_list&list_path=combined_inbox'},
            {'page': '*', 'control_chars': ['meta'], 'char': 70, 'action': ks_redirect, 'target': '?page=message_list&list_path=flagged'},
            {'page': '*', 'control_chars': ['meta'], 'char': 84, 'action': Hm_Folders.toggle_folder_list, 'target': false},
            {'page': '*', 'control_chars': ['meta'], 'char': 72, 'action': ks_redirect, 'target': '?page=history'},
            {'page': '*', 'control_chars': ['meta'], 'char': 83, 'action': ks_redirect, 'target': '?page=compose'},
            {'page': '*', 'control_chars': ['meta'], 'char': 67, 'action': ks_redirect, 'target': '?page=contacts'},
            {'page': '*', 'control_chars': ['meta'], 'char': 85, 'action': ks_redirect, 'target': '?page=message_list&list_path=unread'},
            {'page': 'message_list', 'control_chars': [], 'char': 78, 'action': ks_next_msg_list, 'target': false},
            {'page': 'message_list', 'control_chars': [], 'char': 80, 'action': ks_prev_msg_list, 'target': false},
            {'page': 'message_list', 'control_chars': [], 'char': 13, 'action': ks_load_msg, 'target': false},
            {'page': 'message_list', 'control_chars': [], 'char': 83, 'action': ks_select_msg, 'target': false},
            {'page': 'message_list', 'control_chars': [], 'char': 65, 'action': ks_select_all, 'target': false},
            {'page': 'message_list', 'control_chars': ['shift'], 'char': 82, 'action': ks_click_button, 'target': '.msg_read'},
            {'page': 'message_list', 'control_chars': ['shift'], 'char': 85, 'action': ks_click_button, 'target': '.msg_unread'},
            {'page': 'message_list', 'control_chars': ['shift'], 'char': 70, 'action': ks_click_button, 'target': '.msg_flag'},
            {'page': 'message_list', 'control_chars': ['shift'], 'char': 69, 'action': ks_click_button, 'target': '.msg_unflag'},
            {'page': 'message_list', 'control_chars': ['shift'], 'char': 68, 'action': ks_click_button, 'target': '.msg_delete'},
            {'page': 'message', 'control_chars': [], 'char': 80, 'action': ks_follow_link, 'target': '.plink'},
            {'page': 'message', 'control_chars': [], 'char': 78, 'action': ks_follow_link, 'target': '.nlink'},
            {'page': 'message', 'control_chars': [], 'char': 82, 'action': ks_follow_link, 'target': '.reply_link'},
            {'page': 'message', 'control_chars': ['shift'], 'char': 70, 'action': ks_follow_link, 'target': '.forward_link'},
            {'page': 'message', 'control_chars': [], 'char': 85, 'action': ks_click_button, 'target': '.unflagged_link'},
            {'page': 'message', 'control_chars': [], 'char': 70, 'action': ks_click_button, 'target': '.flagged_link'},
            {'page': 'message', 'control_chars': [], 'char': 68, 'action': ks_click_button, 'target': '.delete_link'},
            {'page': 'message', 'control_chars': ['shift'], 'char': 82, 'action': ks_follow_link, 'target': '.reply_all_link'}
        ];
        $(document).not('input').keydown(function(e) { return Keyboard_Shortcuts.check(e, shortcuts); });
    }
});
