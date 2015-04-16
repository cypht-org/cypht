var imap_delete_action = function() {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
            }
        },
        {'imap_delete': 1}
    );
};

var imap_hide = function() {
    event.preventDefault();
    var form = $(this).parent();
    var server_id = $('.imap_server_id', form).val();
    imap_hide_action(form, server_id, 1);
};

var imap_unhide = function() {
    event.preventDefault();
    var form = $(this).parent();
    var server_id = $('.imap_server_id', form).val();
    imap_hide_action(form, server_id, 0);
};

var imap_hide_action = function(form, server_id, hide) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_debug'},
        {'name': 'imap_server_id', 'value': server_id},
        {'name': 'hide_imap_server', 'value': hide}],
        function(res) {
            if (hide) {
                $('.unhide_imap_connection', form).show();
                $('.hide_imap_connection', form).hide();
            }
            else {
                $('.unhide_imap_connection', form).hide();
                $('.hide_imap_connection', form).show();
            }
            Hm_Folders.reload_folders(true);
        }
    );
};

var imap_save_action = function() {
    event.preventDefault();
    var form = $(this).parent();
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
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
            }
        },
        {'imap_save': 1}
    );
};

var imap_forget_action = function() {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.just_forgot_credentials) {
                form.find('.credentials').prop('disabled', false);
                form.find('.imap_password').val('');
                form.find('.imap_password').prop('placeholder', 'Password');
                form.append('<input type="submit" value="Save" class="save_imap_connection" />');
                $('.save_imap_connection').on('click', imap_save_action);
                $('.forget_imap_connection', form).hide();
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
            }
        },
        {'imap_forget': 1}
    );
};

var imap_setup_server_page = function() {
    $('.imap_delete').on('click', imap_delete_action);
    $('.save_imap_connection').on('click', imap_save_action);
    $('.hide_imap_connection').on('click', imap_hide);
    $('.unhide_imap_connection').on('click', imap_unhide);
    $('.forget_imap_connection').on('click', imap_forget_action);
    $('.test_imap_connect').on('click', imap_test_action);
    var dsp = Hm_Utils.get_from_local_storage('.imap_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.imap_section').css('display', dsp);
    }
};

var imap_test_action = function() {
    $('.imap_folder_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        false,
        {'imap_connect': 1}
    );
};

var imap_flag_message = function(state) {
    var uid = $('.msg_uid').val();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (detail && uid) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_flag_message'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_flag_state', 'value': state},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function() {
                if (state == 'flagged') {
                    $('#flag_msg').show();
                    $('#unflag_msg').hide();
                }
                else {
                    $('#flag_msg').hide();
                    $('#unflag_msg').show();
                }
                set_message_content();
            }
        );
    }
    return false;
};

var imap_status_update = function() {
    var id;
    var i;
    if ($('.imap_server_ids').length) {
        var ids = $('.imap_server_ids').val().split(',');
        if ( ids && ids !== '') {
            var process_result = function(res) {
                var id = res.imap_status_server_id;
                $('.imap_status_'+id).html(res.imap_status_display);
            };
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Ajax.request(
                    [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_status'},
                    {'name': 'imap_server_ids', 'value': id}],
                    process_result
                );
            }
        }
    }
    return false;
};

var imap_message_list_content = function(id, folder, hook, batch_callback) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': hook},
        {'name': 'folder', 'value': folder},
        {'name': 'imap_server_ids', 'value': id}],
        function(res) {
            var ids = res.imap_server_ids.split(',');
            if (folder) {
                var i;
                for (i=0;i<ids.length;i++) {
                    ids[i] = ids[i]+'_'+Hm_Utils.clean_selector(folder);
                }
            }
            Hm_Message_List.update(ids, res.formatted_message_list, 'imap');
        },
        [],
        false,
        batch_callback
    );
    return false;
};

var imap_all_mail_content = function(id, folder) {
    return imap_message_list_content(id, folder, 'ajax_imap_combined_inbox', Hm_Message_List.set_all_mail_state);
};

var imap_search_page_content = function(id, folder) {
    if (hm_search_terms()) {
        return imap_message_list_content(id, folder, 'ajax_imap_search', Hm_Message_List.set_search_state);
    }
    return false;
};

var imap_background_unread_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_unread'},
        {'name': 'imap_server_ids', 'value': id}],
        imap_background_unread_content_result,
        [],
        true
    );
    return false;
};

var imap_background_unread_content_result = function(res) {
    var ids = [res.imap_server_id];
    var cache = $('<tbody></tbody>').append($(Hm_Utils.get_from_local_storage('formatted_unread_data')));
    var count = $('tr', cache).length;
    Hm_Message_List.update(ids, res.formatted_message_list, 'imap', cache);
    Hm_Utils.save_to_local_storage('formatted_unread_data', cache.html());
    if ($('tr', cache).length > count) {
        $('.menu_unread > a').css('font-weight', 'bold');
        Hm_Folders.save_folder_list();
    }
};

var remove_imap_combined_source = function() {
    return update_imap_combined_source(hm_list_path(), 0);
};

var add_imap_combined_source = function() {
    return update_imap_combined_source(hm_list_path(), 1);
};

var update_imap_combined_source = function(path, state) {
    event.preventDefault();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_update_combined_source'},
        {'name': 'list_path', 'value': path},
        {'name': 'combined_source_state', 'value': state}],
        function(res) {
            if (state == 1) {
                $('.add_source').hide();
                $('.remove_source').show();
            }
            else {
                $('.add_source').show();
                $('.remove_source').hide();
            }
        },
        [],
        true
    );
    return false;
};

var imap_combined_unread_content = function(id, folder) {
    return imap_message_list_content(id, folder, 'ajax_imap_unread', Hm_Message_List.set_unread_state);
};

var imap_combined_flagged_content = function(id, folder) {
    return imap_message_list_content(id, folder, 'ajax_imap_flagged', Hm_Message_List.set_flagged_state);
};

var imap_combined_inbox_content = function(id, folder) {
    return imap_message_list_content(id, folder, 'ajax_imap_combined_inbox', Hm_Message_List.set_combined_inbox_state);
};

var setup_imap_folder_page = function() {
    if ($('.message_table tbody tr').length === 0) {
        select_imap_folder(hm_list_path());
    }
    $('.remove_source').click(remove_imap_combined_source);
    $('.add_source').click(add_imap_combined_source);
    $('.message_table tr').show();
};

var select_imap_folder = function(path) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    if (detail) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_display'},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            display_imap_mailbox
        );
    }
    return false;
};

var display_imap_mailbox = function(res) {
    var ids = [res.imap_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'imap');
    if (res.page_links) {
        $('.page_links').html(res.page_links);
    }
    $('input[type=checkbox]').click(function(e) {
        Hm_Message_List.toggle_msg_controls();
        Hm_Message_List.check_select_range(e);
    });
};

var expand_imap_folders = function(path) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+Hm_Utils.clean_selector(detail.folder));
    if ($('li', list).length === 0) {
        $('.expand_link', list).html('-');
        if (detail) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                expand_imap_mailbox,
                [],
                false,
                Hm_Folders.save_folder_list
            );
        }
    }
    else {
        $('.expand_link', list).html('+');
        $('ul', list).remove();
        Hm_Folders.save_folder_list();
    }
    return false;
};

var expand_imap_mailbox = function(res) {
    if (res.imap_expanded_folder_path) {
        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path)).append(res.imap_expanded_folder_formatted);
        $('.imap_folder_link').unbind('click');
        $('.imap_folder_link').click(function() { return expand_imap_folders($(this).data('target')); });
    }
};

var display_msg_content = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.msg_headers);
    $('.msg_text').append(res.msg_text);
    $('.msg_text').append(res.msg_parts);
    set_message_content();
    document.title = $('.header_subject th').text();
    imap_message_view_finished();
};

var set_message_content = function() {
    var path = hm_list_path();
    var msg_uid = hm_msg_uid();
    var key = msg_uid+'_'+path;
    Hm_Utils.save_to_local_storage(key, $('.msg_text').html());
};

var get_local_message_content = function() {
    var path = hm_list_path();
    var msg_uid = hm_msg_uid();
    var key = msg_uid+'_'+path;
    return Hm_Utils.get_from_local_storage(key);
};

var get_message_content = function(msg_part) {
    var uid = $('.msg_uid').val();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (detail && uid) {
        window.scrollTo(0,0);
        $('.msg_text_inner').html('');
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_content'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_msg_part', 'value': msg_part},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            display_msg_content
        );
    }
    return false;
};

var imap_prefetch_message_content = function(uid, server_id, folder) {
    var div;
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_content'},
        {'name': 'imap_msg_uid', 'value': uid},
        {'name': 'imap_msg_part', 'value': ''},
        {'name': 'imap_server_id', 'value': server_id},
        {'name': 'imap_prefetch', 'value': true},
        {'name': 'folder', 'value': folder}],
        function(res) {
            var key = uid+'_imap_'+server_id+'_'+folder;
            if (!Hm_Utils.get_from_local_storage(key)) {
                div = $('<div></div>');
                div.append(res.msg_headers);
                div.append(res.msg_text);
                div.append(res.msg_parts);
                Hm_Utils.save_to_local_storage(key, div.html());
                delete div;
            }
        },
        [],
        true
    );
    return false;
};

var imap_prefetch_msgs = function() {
    var detail;
    var key;
    $(Hm_Utils.get_from_local_storage('formatted_unread_data')).each(function() {
        if ($(this).attr('class').match(/^imap/)) {
            detail = Hm_Utils.parse_folder_path($(this).attr('class'), 'imap');
            key = detail.uid+'_'+detail.type+'_'+detail.server_id+'_'+detail.folder;
            if (!Hm_Utils.get_from_local_storage(key)) {
                imap_prefetch_message_content(detail.uid, detail.server_id, detail.folder);
                return false;
            }
        }
    });
};

var imap_setup_message_view_page = function() {
    var msg_content = get_local_message_content();
    if (!msg_content || !msg_content.length) {
        get_message_content();
    }
    else {
        $('.msg_text').html(msg_content);
        document.title = $('.header_subject th').text();
        imap_message_view_finished();
        imap_read_prefetch();
    }
};

var imap_read_prefetch = function() {
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    var uid = hm_msg_uid();
    var msg_id = 'imap_'+detail.server_id+'_'+uid+'_'+detail.folder;
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_message_action'},
        {'name': 'action_type', 'value': 'read'},
        {'name': 'message_ids', 'value': [msg_id]}],
        false,
        [],
        true
    );
};

var imap_message_view_finished = function() {
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    var class_name = false;
    var msg_uid = hm_msg_uid();
    if (detail) {
        class_name = 'imap_'+detail.server_id+'_'+msg_uid+'_'+detail.folder;
        if (hm_list_parent() == 'combined_inbox') {
            Hm_Message_List.prev_next_links('formatted_combined_inbox', class_name);
        }
        else if (hm_list_parent() == 'unread') {
            Hm_Message_List.prev_next_links('formatted_unread_data', class_name);
        }
        else if (hm_list_parent() == 'flagged') {
            Hm_Message_List.prev_next_links('formatted_flagged_data', class_name);
        }
    }
    if (class_name) {
        Hm_Message_List.track_read_messages(class_name);
    }
    $('.header_toggle').click(function() { return Hm_Utils.toggle_long_headers(); });
    $('.msg_part_link').click(function() { return get_message_content($(this).data('messagePart')); });
    $('#flag_msg').click(function() { return imap_flag_message($(this).data('state')); });
    $('#unflag_msg').click(function() { return imap_flag_message($(this).data('state')); });
};

var imap_setup_compose_page = function() {
    var source = $('.imap_reply_source');
    var uid = $('.imap_reply_uid');
    if (source.length && uid.length) {
        var detail = Hm_Utils.parse_folder_path(source.val(), 'imap');
        if (detail) {
            $('.compose_to').prop('disabled', true);
            $('.smtp_send').prop('disabled', true);
            $('.compose_subject').prop('disabled', true);
            $('.compose_body').prop('disabled', true);
            $('.smtp_server_id').prop('disabled', true);
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_content'},
                {'name': 'imap_msg_uid', 'value': uid.val()},
                {'name': 'reply_format', 'value': 1},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                display_reply_content
            );
        }
    }
};

var display_reply_content = function(res) {
    $('.compose_to').prop('disabled', false);
    $('.smtp_send').prop('disabled', false);
    $('.compose_subject').prop('disabled', false);
    $('.compose_body').prop('disabled', false);
    $('.smtp_server_id').prop('disabled', false);
    $('.compose_body').text(res.reply_body);
    $('.compose_subject').val(res.reply_subject);
    $('.compose_to').val(res.reply_to);
    document.title = res.reply_subject;
};

if (hm_page_name() == 'message_list' && hm_list_path().substring(0, 4) == 'imap') {
    setup_imap_folder_page();
}
else if (hm_page_name() == 'compose') {
    imap_setup_compose_page();
}
else if (hm_page_name() == 'message' && hm_list_path().substr(0, 4) == 'imap') {
    imap_setup_message_view_page();
}
else if (hm_page_name() == 'servers') {
    imap_setup_server_page();
}
else if (hm_page_name() == 'info') {
    setTimeout(imap_status_update, 100);
}

$(function() {
    if (hm_page_name() != 'message_list' && hm_page_name() != 'search' && hm_page_name() != 'wordpress') {
        Hm_Message_List = new New_Hm_Message_List();
        Hm_Message_List.add_sources(hm_data_sources());
        Hm_Timer.add_job(Hm_Message_List.load_sources, 60, true);
    }
    Hm_Timer.add_job(imap_prefetch_msgs, 83, true);
});
