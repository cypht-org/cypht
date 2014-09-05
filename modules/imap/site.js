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
                $('.forget_imap_connection', form).fadeOut(200);
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
        update_imap_unread_display,
        [],
        false,
        set_unread_state
    );
    return false;
};

var update_imap_unread_display = function(res) {
    var ids = res.unread_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_unread_data, 'imap');
};

/* flagged page */
var imap_combined_flagged_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_flagged'},
        {'name': 'imap_server_ids', 'value': id}],
        update_flagged_message_display,
        [],
        false,
        set_flagged_state
    );
    return false;
};

var update_flagged_message_display = function(res) {
    var ids = res.flagged_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_flagged_data, 'imap');
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

/* combined inbox page */
var imap_combined_inbox_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_combined_inbox'},
        {'name': 'imap_server_ids', 'value': id}],
        display_imap_combined_inbox,
        [],
        false,
        set_combined_inbox_state
    );
    return false;
};

var display_imap_combined_inbox = function(res) {
    var ids = res.combined_inbox_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_combined_inbox, 'imap');
};

/* imap mailbox list */
var setup_imap_folder_page = function() {
    if ($('.message_table tbody tr').length == 0) {
        select_imap_folder(hm_list_path, true);
    }
    $('.message_table tr').fadeIn(100);
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
    if (res.page_links) {
        $('.page_links').html(res.page_links);
    }
    $(':checkbox').click(function(e) {
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
        $('ul', list).fadeOut(200, function() { $(this).remove(); save_folder_list(); });
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
    document.title = 'HM3 '+$('.header_subject th').text();
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
        document.title = 'HM3 '+$('.header_subject th').text();
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
            var count = update_unread_cache(class_name);
        }
    }
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

/* to move */
var toggle_rows = function() {
    $(':checkbox').each(function () { this.checked = !this.checked; });
    Hm_Message_List.toggle_msg_controls();
    return false;
};
var update_unread_cache = function(class_name) {
    count = Hm_Message_List.remove_from_cache('formatted_unread_data', class_name);
    return count;
};

var toggle_long_headers = function() {
    $('.long_header').toggle(300);
    $('.header_toggle').toggle(0);
    return false;
};

var update_unread_count = function() {
    var id;
    var ids = $('.imap_server_ids').val();
    if (ids && ids.length > 0) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_unread_count'},
            {'name': 'imap_server_ids', 'value': ids}],
            update_unread_count_display,
            [],
            true
        );
    }
    else {
        console.log('No imap server ids');
    }
};

var update_unread_count_display = function(res) {
    if (res.unseen_total) {
        var current = $('.unread_count').text();
        if (current != res.unseen_total) {
            $('.unread_count').text(res.unseen_total);
            save_folder_list();
        }
    }
};

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
        add_imap_sources(imap_combined_inbox_content);
    }
    else if (hm_list_path.substring(0, 4) == 'imap') {
        setup_imap_folder_page();
    }
}
else if (hm_page_name == 'message' && hm_list_path.substr(0, 4) == 'imap') {
    setup_message_view_page();
}
else if (hm_page_name == 'servers') {
    setup_server_page();
}
else if (hm_page_name == 'home') {
    imap_status_update();
}
/*if (hm_page_name != 'message_list' || hm_list_path != 'unread') {
    Hm_Timer.add_job(update_unread_count, 60, true);
}*/
