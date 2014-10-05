/* server page actions */
var imap_delete_action = function() {
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
                reload_folders(true);
            }
        },
        {'imap_delete': 1}
    );
};

var imap_save_action = function() {
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
                reload_folders(true);
            }
        },
        {'imap_save': 1}
    );
};

var imap_forget_action = function() {
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
                $('.forget_imap_connection', form).hide();
                reload_folders(true);
            }
        },
        {'imap_forget': 1}
    );
};

var setup_server_page = function() {
    $('.imap_delete').on('click', imap_delete_action);
    $('.save_imap_connection').on('click', imap_save_action);
    $('.forget_imap_connection').on('click', imap_forget_action);
    $('.test_imap_connect').on('click', imap_test_action);
    var dsp = get_from_local_storage('.imap_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.imap_section').css('display', dsp);
    }
};

var imap_test_action = function() {
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

/* unread page */
var imap_combined_unread_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_unread'},
        {'name': 'imap_server_ids', 'value': id}],
        display_imap_message_list,
        [],
        false,
        set_unread_state
    );
    return false;
};

/* flagged page */
var imap_combined_flagged_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_flagged'},
        {'name': 'imap_server_ids', 'value': id}],
        display_imap_message_list,
        [],
        false,
        set_flagged_state
    );
    return false;
};

/* home page */
var imap_status_update = function() {
    if ($('.imap_server_ids').length) {
        var ids = $('.imap_server_ids').val().split(',');
        if ( ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Ajax.request(
                    [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_status'},
                    {'name': 'imap_server_ids', 'value': id}],
                    update_imap_status_display,
                    [],
                    false
                );
            }
        }
    }
    return false;
};

var update_imap_status_display = function(res) {
    var id = res.imap_status_server_id;
    $('.imap_status_'+id).html(res.imap_status_display);
};

var imap_search_page_content = function(id) {
    if (hm_search_terms && hm_search_terms.length) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_search'},
            {'name': 'imap_server_ids', 'value': id}],
            display_imap_message_list,
            [],
            false,
            false
        );
    }
    return false;
};

var display_imap_message_list = function(res) {
    var ids = res.imap_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_message_list, 'imap');
}

/* all mail page */
var imap_all_mail_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_combined_inbox'},
        {'name': 'imap_server_ids', 'value': id}],
        display_imap_message_list,
        [],
        false,
        set_all_mail_state
    );
    return false;
};

/* combined inbox page */
var imap_combined_inbox_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_combined_inbox'},
        {'name': 'imap_server_ids', 'value': id}],
        display_imap_message_list,
        [],
        false,
        set_combined_inbox_state
    );
    return false;
};

/* imap mailbox list */
var setup_imap_folder_page = function() {
    if ($('.message_table tbody tr').length == 0) {
        select_imap_folder(hm_list_path, true);
    }
    $('.message_table tr').show();
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
    var count = Hm_Message_List.update(ids, res.formatted_message_list, 'imap');
    if (res.page_links) {
        $('.page_links').html(res.page_links);
    }
    $('input[type=checkbox]').click(function(e) {
        Hm_Message_List.toggle_msg_controls();
        Hm_Message_List.check_select_range(e);
    });
};

/* folder list  */
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
        $('ul', list).remove();
        save_folder_list();
    }
    return false;
};

var expand_imap_mailbox = function(res) {
    $('.'+clean_selector(res.imap_expanded_folder_path)).append(res.imap_expanded_folder_formatted);
};

/* message content */
var display_msg_content = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.msg_gravatar);
    $('.msg_text').append(res.msg_headers);
    $('.msg_text').append(res.msg_text);
    $('.msg_text').append(res.msg_parts);
    set_message_content();
    document.title = $('.header_subject th').text();
    imap_message_view_finished();
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

var setup_message_view_page = function() {
    var msg_content = get_local_message_content();
    if (!msg_content || !msg_content.length) {
        get_message_content();
    }
    else {
        $('.msg_text').html(msg_content);
        document.title = $('.header_subject th').text();
        imap_message_view_finished();
    }
};

var imap_message_view_finished = function() {
    detail = parse_folder_path(hm_list_path, 'imap');
    if (detail) {
        class_name = 'imap_'+detail.server_id+'_'+hm_msg_uid+'_'+detail.folder;
        if (hm_list_parent == 'combined_inbox') {
            prev_next_links('formatted_combined_inbox', class_name);
        }
        else if (hm_list_parent == 'unread') {
            prev_next_links('formatted_unread_data', class_name);
        }
        else if (hm_list_parent == 'flagged') {
            prev_next_links('formatted_flagged_data', class_name);
        }
    }
    track_read_messages(class_name);
};

var add_imap_sources = function(callback) {
    if ($('.imap_server_ids').length) {
        var id;
        var ids = $('.imap_server_ids').val().split(',');
        if (ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Message_List.sources.push({type: 'imap', id: id, callback: callback});
            }
        }
    }
};

var setup_compose_page = function() {
    var source = $('.imap_reply_source');
    var uid = $('.imap_reply_uid');
    if (source.length && uid.length) {
        detail = parse_folder_path(source.val(), 'imap');
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
                display_reply_content,
                [],
                false
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
}

/* setup */
if (hm_page_name == 'message_list') {
    if (hm_list_path == 'combined_inbox') {
        add_imap_sources(imap_combined_inbox_content);
    }
    else if (hm_list_path == 'unread') {
        add_imap_sources(imap_combined_unread_content);
    }
    else if (hm_list_path == 'flagged') {
        add_imap_sources(imap_combined_flagged_content);
    }
    else if (hm_list_path == 'email') {
        add_imap_sources(imap_all_mail_content);
    }
    else if (hm_list_path.substring(0, 4) == 'imap') {
        setup_imap_folder_page();
    }
}
else if (hm_page_name == 'search') {
    add_imap_sources(imap_search_page_content);
}
else if (hm_page_name == 'compose') {
    setup_compose_page();
}
else if (hm_page_name == 'message' && hm_list_path.substr(0, 4) == 'imap') {
    setup_message_view_page();
}
else if (hm_page_name == 'servers') {
    setup_server_page();
}
else if (hm_page_name == 'home') {
    setTimeout(imap_status_update, 100);
}
