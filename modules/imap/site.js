var imap_delete_action = function() {
    $('.imap_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
            }
        },
        {'imap_delete': 1}
    );
};

var imap_save_action = function() {
    $('.imap_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('.save_imap_connection').hide();
                form.find('.imap_password').val('');
                form.find('.imap_password').attr('placeholder', '[saved]');
                form.append('<input type="submit" value="Forget" class="forget_imap_connection" />');
                $('.forget_imap_connection').on('click', imap_forget_action);
            }
        },
        {'imap_save': 1}
    );
};

var imap_forget_action = function() {
    $('.imap_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.just_forgot_credentials) {
                form.find('.credentials').attr('disabled', false);
                form.find('.imap_password').val('');
                form.find('.imap_password').attr('placeholder', 'Password');
                form.append('<input type="submit" value="Save" class="save_imap_connection" />');
                $('.save_imap_connection').on('click', imap_save_action);
                $('.forget_imap_connection', form).remove();
            }
        },
        {'imap_forget': 1}
    );
};

var imap_test_action = function() {
    $('.imap_debug_data').empty();
    $('.imap_folder_data').empty();
    Hm_Notices.show({0: 'Testing connection...'});
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        false,
        {'imap_connect': 1}
    );
};

var format_time = function(s) {
    s = s.replace(/second(s|)/, '1');
    s = s.replace(/minute(s|)/, '60');
    s = s.replace(/hour(s|)/, '3600');
    parts = s.split(',');
    total = 0;
    for (i=0;i<parts.length;i++) {
        section = parts[i].trim();
        numbers = section.split(' ');
        if (numbers.length == 2) {
            total += (numbers[0]*1 * numbers[1]*1);
        }
    }
    return total;
};
var unread_sort = function(s1, s2) {
    return format_time(s2) >= format_time(s1);
};
var unread_insert = function(row) {
    timestr = $('.msg_date', $(row)).html();
    element = false;
    $('.message_table tbody tr').each(function() {
        timestr2 = $('.msg_date', $(this)).html();
        if (unread_sort(timestr, timestr2)) {
            element = $(this);
            return false;
        }
    });
    if (element) {
        $(row).insertBefore(element);
    }
    else {
        $('.message_table tbody').append(row);
    }
};

var update_unread_message_display = function(res) {
    var ids = res.unread_server_ids.split(',');
    var msg_ids = [];
    if (res.formatted_unread_data && !jQuery.isEmptyObject(res.formatted_unread_data)) {
        $('.empty_list').remove();
    }
    for (index in res.formatted_unread_data) {
        row = res.formatted_unread_data[index][0];
        id = res.formatted_unread_data[index][1];
        if (!$('.'+clean_selector(id)).length) {
            unread_insert(row);
            $('.'+clean_selector(id)).fadeIn(600);
        }
        else {
            timestr = $('.msg_date', $(row)).html();
            $('.msg_date', $('.'+clean_selector(id))).html(timestr);
        }
        msg_ids.push(id);
    }
    var count = $('.message_table tbody tr').length;
    for (i=0;i<ids.length;i++) {
        $('.message_table tbody tr[class^=imap_'+ids[i]+'_]').filter(function() {
            var id = this.className;
            if (jQuery.inArray(id, msg_ids) == -1) {
                count--;
                $(this).remove();
            }
        });
    }
    document.title = 'HM3 '+count+' Unread';
    $('.sys_messages').html($('.sys_messages').html()+'.');
};

var imap_unread_update = function(loading) {
    var ids = $('#imap_server_ids').val().split(',');
    if ( ids && ids != '') {
        Hm_Notices.show({0: 'Updating unread messages '});
        for (i=0;i<ids.length;i++) {
            id=ids[i];
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_unread'},
                {'name': 'imap_server_ids', 'value': id}],
                update_unread_message_display,
                [],
                loading,
                set_unread_state
            );
        }
    }
    return false;
};

var toggle_long_headers = function() {
    $('.long_header').toggle(300);
    $('.header_toggle').toggle(0);
    return false;
};

var select_imap_folder = function(path, force) {
    var detail = parse_folder_path(path, 'imap');
    if (detail) {
        if (force) {
            Hm_Notices.show({0: 'Loading messages ...'});
        }
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_display'},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'force_update', 'value': force},
            {'name': 'folder', 'value': detail.folder}],
            display_imap_mailbox,
            [],
            false
        );
    }
    return false;
};

var display_imap_mailbox = function(res) {
    Hm_Notices.hide(true);
    var imap_id = res.imap_server_id;
    var msg_ids = [];
    for (index in res.formatted_mailbox_page) {
        row = res.formatted_mailbox_page[index][0];
        id = res.formatted_mailbox_page[index][1];
        if (!$('.'+clean_selector(id)).length) {
            $('.message_table tbody').append(row);
            $('.'+clean_selector(id)).fadeIn(600);
        }
        msg_ids.push(id);
    }
    $('.message_table tbody tr[class^=imap_'+imap_id+'_]').filter(function() {
        var id = this.className;
        if (jQuery.inArray(id, msg_ids) == -1) {
            $(this).fadeOut(600, function() { $('.'+id).remove(); });
        }
    });
    if (res.imap_page_links) {
        $('.imap_page_links').html(res.imap_page_links);
    }
    $(':checkbox').click(function() {
        toggle_msg_controls();
    });
};

var expand_imap_folders = function(path) {
    var detail = parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+clean_selector(detail.folder));
    if ($('li', list).length == 0) {
        $('.expand_link', list).html('-');
        if (detail) {
            Hm_Notices.show({0: 'Loading folder ...'});
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                expand_imap_mailbox,
                [],
                false,
                save_folder_list
            );
        }
    }
    else {
        $('.expand_link', list).html('+');
        $('ul', list).remove();
        save_folder_list();
    }
    return false;
};

var save_folder_list = function() {
    var folders = $('.imap_folders').html();
    $('*', folders).removeClass('selected_menu');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_save_folder_state'},
        {'name': 'imap_folder_state', 'value': folders}],
        false,
        [],
        true
    );
};

var toggle_rows = function() {
    $(':checkbox').each(function () { this.checked = !this.checked; });
    toggle_msg_controls();
    return false;
};
var expand_imap_mailbox = function(res) {
    $('.'+clean_selector(res.imap_expanded_folder_path)).append(res.imap_expanded_folder_formatted);
};

var display_msg_content = function(res) {
    Hm_Notices.hide(true);
    $('.msg_text').html('');
    $('.msg_text').append(res.msg_gravatar);
    $('.msg_text').append(res.msg_headers);
    $('.msg_text').append(res.msg_text);
    $('.msg_text').append(res.msg_parts);
    document.title = 'HM3 '+$('.content_title').text();
};

var get_message_content = function(msg_part) {
    var uid = $('.msg_uid').val();
    var detail = parse_folder_path(hm_list_path, 'imap');
    if (detail && uid) {
        window.scrollTo(0,0);
        $('.msg_text_inner').html('');
        Hm_Notices.show({0: 'Fetching message ... '});
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_content'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_msg_part', 'value': msg_part},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            display_msg_content,
            [],
            false
        );
    }
    return false;
};

var set_unread_state = function() {
    Hm_Notices.hide(true);
    if (!$('.message_table tr').length) {
        if (!$('.empty_list').length) {
            $('.message_list').append('<div class="empty_list">No unread messages found!</div>');
        }
    }
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    var request = function() {
            Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_save_unread_state'},
            {'name': 'formatted_unread_data', 'value': data.html()}],
            false,
            [],
            true
        );
    };
    $(':checkbox').click(function() {
        toggle_msg_controls();
    });
    setTimeout(request, 1000);
};

var toggle_msg_controls = function() {
    if ($('input:checked').length > 0) {
        $('.msg_controls a').removeClass('disabled_link');
    }
    else {
        $('.msg_controls a').addClass('disabled_link');
    }
};

var update_message_list = function(res) {
};

var imap_message_action = function(action_type) {
    var msg_list = $('.message_list');
    var selected = [];
    $(':checked', msg_list).each(function() {
        selected.push($(this).val());
    });
    if (selected.length > 0) {
        Hm_Notices.show({0: 'Performing message action ...'});
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_action'},
            {'name': 'imap_action_type', 'value': action_type},
            {'name': 'imap_message_ids', 'value': selected}],
            update_message_list,
            [],
            false
        );
    }
    return false;
};

var get_from_local_storage = function(key) {
    return localStorage.getItem(key);
};

var save_to_local_storage = function(key, val) {
    if (typeof(Storage) !== "undefined") {
        localStorage.setItem(key, val);
    }
    return false;
};

if (hm_page_name == 'message_list') {
    $(':checkbox').each(function () { this.checked = false; });
    if (hm_list_path == 'unread') {
        $('.menu_unread').addClass('selected_menu');
        if ($('.message_table tbody tr').length > 0) {
            defer = true;
            document.title = 'HM3 '+$('.message_table tbody tr').length+' Unread';
        }
        else {
            defer = false;
        }
        Hm_Timer.add_job(imap_unread_update, 60, defer);
        $('.message_table tr').fadeIn(100);
    }
    else if (hm_list_path == 'flagged') {
        $('.menu_flagged').addClass('selected_menu');
    }
    else if (hm_list_path.substring(0, 4) == 'imap') {
        $('a:eq(0)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
        $('a:eq(1)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
        if ($('.message_table tbody tr').length == 0) {
            select_imap_folder(hm_list_path, true);
        }
        $('.message_table tr').fadeIn(100);
    }
    $(':checkbox').click(function() {
        toggle_msg_controls();
    });

}
else if (hm_page_name == 'message') {
    if (hm_list_path.substring(0, 4) == 'imap') {
        if ($('.msg_text').children().length == 0) {
            get_message_content();
        }
        else {
            document.title = 'HM3 '+$('.content_title').text();
        }
        if (hm_list_path.substring(0, 4) == 'imap') {
            $('a:eq(0)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
            $('a:eq(1)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
        }
    }
}
else if (hm_page_name == 'servers') {
    $('.imap_delete').on('click', imap_delete_action);
    $('.save_imap_connection').on('click', imap_save_action);
    $('.forget_imap_connection').on('click', imap_forget_action);
    $('.test_imap_connect').on('click', imap_test_action);
}
