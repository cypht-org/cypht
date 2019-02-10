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
        var control_keys = {'alt': e.altKey, 'shift': e.shiftKey, 'meta': e.metaKey, 'control': e.ctrlKey};
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
                if (combo['action'] == 'unfocus') {
                    Keyboard_Shortcuts.unfocus();
                    return false;
                }
                if (Keyboard_Shortcuts.in_input_tag(e)) {
                    return true;
                }
                Keyboard_Actions[combo['action']](combo['target']);
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

var Keyboard_Actions = {
    'unfocus': false,
    'redirect': ks_redirect,
    'toggle': Hm_Folders.toggle_folder_list,
    'next': ks_next_msg_list,
    'prev': ks_prev_msg_list,
    'load': ks_load_msg,
    'select': ks_select_msg,
    'select_all': ks_select_all,
    'click': ks_click_button,
    'link': ks_follow_link
};

$(function() {

    if (typeof shortcuts != 'undefined') {
        $(document).not('input').on('keydown', function(e) { return Keyboard_Shortcuts.check(e, shortcuts); });
    }
    if (hm_page_name() == 'shortcuts') {
        $('.reset_shortcut').on("click", function() {
            window.location.href = '?page=shortcuts';
        });
    }
});
