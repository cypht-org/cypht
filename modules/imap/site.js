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
                $('.forget_imap_connection', form).fadeOut(200);
            }
        },
        {'imap_forget': 1}
    );
};

var imap_test_action = function() {
    $('.imap_debug_data').empty();
    $('.imap_folder_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        false,
        {'imap_connect': 1}
    );
};

var insert_into_message_list = function(row) {
    var timestr = $('.msg_timestamp', $(row)).val();
    var element = false;
    $('.message_table tbody tr').each(function() {
        timestr2 = $('.msg_timestamp', $(this)).val();
        if ((timestr*1) >= (timestr2*1)) {
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

var set_unread_count = function(count) {
    if (!count && count !== 0) {
        if (hm_list_path == 'unread') {
            count = $('.message_list tbody tr').length;
        }
        else {
            count = get_from_local_storage('unread_count');
            if (!count) {
                count = 0;
            }
        }
    }
    $('.unread_count').text(count);
    save_to_local_storage('unread_count', count);
};

var update_unread_message_display = function(res) {
    var ids = res.unread_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_unread_data, 'imap');
    document.title = 'HM3 '+count+' Unread';
    set_unread_count(count);
};

var update_flagged_message_display = function(res) {
    var ids = res.flagged_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_flagged_data, 'imap');
    document.title = 'HM3 '+count+' Flagged';
};

var update_imap_status_display = function(res) {
    var id = res.imap_status_server_id;
    $('.imap_status_'+id).html(res.imap_status_display);
};

var end_status_update = function() {
};

var imap_status_update = function() {
    var ids = $('.imap_server_ids').val().split(',');
    if ( ids && ids != '') {
        for (i=0;i<ids.length;i++) {
            id=ids[i];
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_status'},
                {'name': 'imap_server_ids', 'value': id}],
                update_imap_status_display,
                [],
                false,
                end_status_update
            );
        }
    }
    return false;
};

var imap_unread_update = function() {
    Hm_Timer.cancel(imap_unread_update);
    var since = $('.unread_since option:selected').val();
    var ids = $('.imap_server_ids').val().split(',');
    if ( ids && ids != '') {
        for (i=0;i<ids.length;i++) {
            id=ids[i];
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_unread'},
                {'name': 'imap_unread_since', 'value': since},
                {'name': 'imap_server_ids', 'value': id}],
                update_unread_message_display,
                [],
                false,
                set_unread_state
            );
        }
    }
    return false;
};

var display_imap_combined_inbox = function(res) {
    var ids = res.combined_inbox_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_combined_inbox, 'imap');
};

var set_combined_inbox_state = function() {
    if (!$('.message_table tr').length) {
        if (!$('.empty_list').length) {
            $('.message_list').append('<div class="empty_list">No messages found!</div>');
        }
    }
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_combined_inbox', data.html());
    $(':checkbox').click(function() {
        Hm_Message_List.toggle_msg_controls();
    });
};

var imap_combined_inbox = function(path, force) {
    var ids = $('.imap_server_ids').val().split(',');
    if (ids && ids != '') {
        for (i=0;i<ids.length;i++) {
            id=ids[i];
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_combined_inbox'},
                {'name': 'imap_server_ids', 'value': id}],
                display_imap_combined_inbox,
                [],
                false,
                set_combined_inbox_state
            );
        }
    }
    return false;
};

var imap_flagged_update = function(loading) {
    var ids = $('.imap_server_ids').val().split(',');
    if (ids && ids != '') {
        for (i=0;i<ids.length;i++) {
            id=ids[i];
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_flagged'},
                {'name': 'imap_server_ids', 'value': id}],
                update_flagged_message_display,
                [],
                loading,
                set_flagged_state
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
    var ids = [res.imap_server_id];
    var count = Hm_Message_List.update(ids, res.formatted_mailbox_page, 'imap');
    if (res.imap_page_links) {
        $('.imap_page_links').html(res.imap_page_links);
    }
    $(':checkbox').click(function() {
        Hm_Message_List.toggle_msg_controls();
    });
};

var expand_imap_folders = function(path) {
    var detail = parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+clean_selector(detail.folder));
    if ($('li', list).length == 0) {
        $('.expand_link', list).html('-');
        if (detail) {
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
        $('ul', list).fadeOut(200, function() { $(this).remove(); save_folder_list(); });
    }
    return false;
};

var save_folder_list = function() {
    save_to_local_storage('formatted_folder_list', $('.folder_list').html());
};

var toggle_rows = function() {
    $(':checkbox').each(function () { this.checked = !this.checked; });
    Hm_Message_List.toggle_msg_controls();
    return false;
};
var expand_imap_mailbox = function(res) {
    $('.'+clean_selector(res.imap_expanded_folder_path)).append(res.imap_expanded_folder_formatted);
};

var display_msg_content = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.msg_gravatar);
    $('.msg_text').append(res.msg_headers);
    $('.msg_text').append(res.msg_text);
    $('.msg_text').append(res.msg_parts);
    set_message_content(res);
    document.title = 'HM3 '+$('.header_subject th').text();
};

var set_message_content = function() {
    var key = hm_msg_uid+'_'+hm_list_path;
    save_to_local_storage(key, $('.msg_text').html());
};
var get_local_message_content = function() {
    var key = hm_msg_uid+'_'+hm_list_path;
    return get_from_local_storage(key);
};

var get_message_content = function(msg_part) {
    var uid = $('.msg_uid').val();
    var detail = parse_folder_path(hm_list_path, 'imap');
    if (detail && uid) {
        window.scrollTo(0,0);
        $('.msg_text_inner').html('');
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

var set_flagged_state = function() {
    if (!$('.message_table tr').length) {
        if (!$('.empty_list').length) {
            $('.message_list').append('<div class="empty_list">No flagged messages found!</div>');
        }
    }
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_flagged_data', data.html());
    $(':checkbox').click(function() {
        Hm_Message_List.toggle_msg_controls();
    });
};

var set_unread_state = function() {
    if (!$('.message_table tr').length) {
        if (!$('.empty_list').length) {
            $('.message_list').append('<div class="empty_list">No unread messages found!</div>');
        }
    }
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_unread_data', data.html());
    $(':checkbox').click(function() {
        Hm_Message_List.toggle_msg_controls();
    });
    Hm_Timer.add_job(imap_unread_update, 60, true);
};

var imap_message_action = function(action_type) {
    var msg_list = $('.message_list');
    var selected = [];
    $('input[type=checkbox]:checked', msg_list).each(function() {
        selected.push($(this).val());
    });
    if (selected.length > 0) {
        Hm_Message_List.update_after_action(action_type, selected);
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_action'},
            {'name': 'imap_action_type', 'value': action_type},
            {'name': 'imap_message_ids', 'value': selected}],
            false,
            [],
            false
        );
    }
    return false;
};

var update_unread_cache = function(class_name) {
    count = Hm_Message_List.remove_from_cache('formatted_unread_data', class_name);
    set_unread_count(count);
};

var setup_unread_page = function() {
    var unread_data = get_from_local_storage('formatted_unread_data');
    if (unread_data && unread_data.length) {
        $('.message_table tbody').html(unread_data);
        defer = true;
        document.title = 'HM3 '+$('.message_table tbody tr').length+' Unread';
        set_unread_count($('.message_table tbody tr').length);
        Hm_Message_List.reset_checkboxes();
    }
    else {
        if (initial_error) {
            defer = true;
        }
        else {
            defer = false;
        }
    }
    Hm_Timer.add_job(imap_unread_update, 60, defer);
    $('.message_table tr').fadeIn(100);
};

var setup_combined_inbox = function() {
    var combined_inbox_data = get_from_local_storage('formatted_combined_inbox');
    if (!combined_inbox_data || !combined_inbox_data.length) {
        imap_combined_inbox();
    }
    else {
        $('.message_table tbody').html(combined_inbox_data);
        Hm_Message_List.reset_checkboxes();
    }
};

var setup_flagged_page = function() {
    var flagged_data = get_from_local_storage('formatted_flagged_data');
    if (!flagged_data || !flagged_data.length) {
        imap_flagged_update();
    }
    else {
        $('.message_table tbody').html(flagged_data);
        Hm_Message_List.reset_checkboxes();
    }
};

var setup_imap_folder_page = function() {
    if ($('.message_table tbody tr').length == 0) {
        select_imap_folder(hm_list_path, true);
    }
    $('.message_table tr').fadeIn(100);
};

var setup_message_view_page = function() {
    var msg_content = get_local_message_content();
    if (!msg_content || !msg_content.length) {
        get_message_content();
    }
    else {
        $('.msg_text').html(msg_content);
        document.title = 'HM3 '+$('.header_subject th').text();
    }
    detail = parse_folder_path(hm_list_path, 'imap');
    if (detail) {
        class_name = 'imap_'+detail.server_id+'_'+hm_msg_uid+'_'+detail.folder;
        update_unread_cache(class_name);
    }
};

var setup_server_page = function() {
    $('.imap_delete').on('click', imap_delete_action);
    $('.save_imap_connection').on('click', imap_save_action);
    $('.forget_imap_connection').on('click', imap_forget_action);
    $('.test_imap_connect').on('click', imap_test_action);
};

var initial_error = $('.err').length;

if (hm_page_name == 'message_list') {
    Hm_Message_List.reset_checkboxes();
    if (hm_list_path == 'unread') {
        setup_unread_page();
    }
    else if (hm_list_path == 'combined_inbox') {
        setup_combined_inbox();
    }
    else if (hm_list_path == 'flagged') {
        setup_flagged_page();
    }
    else if (hm_list_path.substring(0, 4) == 'imap') {
        setup_imap_folder_page();
    }
}
else if (hm_page_name == 'message') {
    setup_message_view_page();
}
else if (hm_page_name == 'servers') {
    setup_server_page();
}
else if (hm_page_name == 'home') {
    if (!initial_error) {
        imap_status_update();
    }
}
set_unread_count();
