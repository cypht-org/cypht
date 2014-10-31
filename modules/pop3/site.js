var pop3_test_action = function() {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function() { },
        {'pop3_connect': 1}
    );
};

var pop3_save_action = function() {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('.save_pop3_connection').hide();
                form.find('.pop3_password').val('');
                form.find('.pop3_password').attr('placeholder', '[saved]');
                form.append('<input type="submit" value="Forget" class="forget_pop3_connection" />');
                $('.forget_pop3_connection').on('click', pop3_forget_action);
                set_unsaved_changes(1);
                reload_folders(true);
            }
        },
        {'pop3_save': 1}
    );
};

var pop3_forget_action = function() {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.just_forgot_credentials) {
                form.find('.credentials').attr('disabled', false);
                form.find('.pop3_password').val('');
                form.find('.pop3_password').attr('placeholder', 'Password');
                form.append('<input type="submit" value="Save" class="save_pop3_connection" />');
                $('.save_pop3_connection').on('click', pop3_save_action);
                $('.forget_pop3_connection', form).remove();
                set_unsaved_changes(1);
                reload_folders(true);
            }
        },
        {'pop3_forget': 1}
    );
};

var pop3_delete_action = function() {
    event.preventDefault();
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
                set_unsaved_changes(1);
                reload_folders(true);
            }
        },
        {'pop3_delete': 1}
    );
};

var display_pop3_mailbox = function(res) {
    var ids = [res.pop3_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'pop3');
    var key = 'pop3_'+res.pop3_server_id;
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage(key, data.html());
};

var load_pop3_list = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_folder_display'},
        {'name': 'pop3_server_id', 'value': id}],
        display_pop3_mailbox,
        [],
        false
    );
    return false;
};

var pop3_message_view = function() {
    $('.msg_text_inner').html('');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_message_display'},
        {'name': 'pop3_list_path', 'value': hm_list_path},
        {'name': 'pop3_uid', 'value': hm_msg_uid}],
        display_pop3_message,
        [],
        false
    );
    return false;
};

var display_pop3_message = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.msg_headers);
    $('.msg_text').append(res.msg_text);
    set_message_content();
    document.title = $('.header_subject th').text();
    pop3_message_view_finished();
};

var pop3_message_view_finished = function() {
    var detail = parse_folder_path(hm_list_path, 'pop3');
    if (detail) {
        var class_name = 'pop3_'+detail.server_id+'_'+hm_msg_uid;
        if (hm_list_parent == 'combined_inbox') {
            prev_next_links('formatted_combined_inbox', class_name);
        }
        else if (hm_list_parent == 'unread') {
            prev_next_links('formatted_unread_data', class_name);
        }
    }
    $('.header_toggle').click(function() { return toggle_long_headers(); });
    $('.msg_part_link').click(function() { return get_message_content($(this).data('messagePart')); });
};

var add_pop3_sources = function(callback) {
    var i;
    var id;
    if ($('.pop3_server_ids').length && $('.pop3_server_ids').val().length) {
        var ids = $('.pop3_server_ids').val().split(',');
        if (ids && ids !== '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Message_List.sources.push({type: 'pop3', id: id, callback: callback});
            }
        }
    }
};
var pop3_all_mail_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_combined_inbox'},
        {'name': 'pop3_server_id', 'value': id}],
        display_pop3_list,
        [],
        false,
        set_all_mail_state
    );
    return false;
};

var pop3_combined_inbox_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_combined_inbox'},
        {'name': 'pop3_server_id', 'value': id}],
        display_pop3_list,
        [],
        false,
        set_combined_inbox_state
    );
    return false;
};

var display_pop3_list = function(res) {
    var ids = [res.pop3_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'pop3');
};

var pop3_status_update = function() {
    var i;
    var id;
    if ($('.pop3_server_ids').length) {
        var ids = $('.pop3_server_ids').val().split(',');
        if ( ids && ids !== '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Ajax.request(
                    [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_status'},
                    {'name': 'pop3_server_ids', 'value': id}],
                    update_pop3_status_display,
                    [],
                    false
                );
            }
        }
    }
    return false;
};

var update_pop3_status_display = function(res) {
    var id = res.pop3_status_server_id;
    $('.pop3_status_'+id).html(res.pop3_status_display);
};

var pop3_search_page_content = function(id) {
    if (hm_search_terms) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_combined_inbox'},
            {'name': 'pop3_search', 'value': 1},
            {'name': 'pop3_server_id', 'value': id}],
            update_pop3_search_result,
            [],
            false,
            false
        );
    }
    return false;
};

var update_pop3_search_result = function(res) {
    var ids = [res.pop3_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'pop3');
};

var pop3_combined_unread_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_unread'},
        {'name': 'pop3_unread_only', 'value': 1},
        {'name': 'pop3_server_id', 'value': id}],
        update_pop3_unread_display,
        [],
        false,
        set_unread_state
    );
    return false;
};

var update_pop3_unread_display = function(res) {
    var ids = [res.pop3_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'pop3');
};

var expand_pop3_settings = function() {
    var dsp = get_from_local_storage('.pop3_setting');
    if (dsp == 'table-row' || dsp == 'none') {
        $('.pop3_setting').css('display', dsp);
    }
};

if (hm_page_name == 'servers') {
    $('.test_pop3_connect').on('click', pop3_test_action);
    $('.save_pop3_connection').on('click', pop3_save_action);
    $('.forget_pop3_connection').on('click', pop3_forget_action);
    $('.delete_pop3_connection').on('click', pop3_delete_action);
    var dsp = get_from_local_storage('.pop3_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.pop3_section').css('display', dsp);
    }
}
else if (hm_page_name == 'message_list') {
    if (hm_list_path == 'combined_inbox') {
        add_pop3_sources(pop3_combined_inbox_content);
    }
    else if (hm_list_path == 'email') {
        add_pop3_sources(pop3_all_mail_content);
    }
    else if (hm_list_path == 'unread') {
        add_pop3_sources(pop3_combined_unread_content);
    }
    else if (hm_list_path.substring(0, 4) == 'pop3') {
        if ($('.message_table tbody tr').length === 0) {
            var detail = parse_folder_path(hm_list_path, 'pop3');
            if (detail) {
                Hm_Message_List.sources.push({type: 'pop3', id: detail.server_id, callback: load_pop3_list});
            }
            Hm_Message_List.setup_combined_view(hm_list_path);
            
        }
        $('.message_table tr').show();
    }
}
else if (hm_page_name == 'search') {
    add_pop3_sources(pop3_search_page_content);
}
else if (hm_page_name == 'message' && hm_list_path.substr(0, 4) == 'pop3') {
    pop3_message_view();
}
else if (hm_page_name == 'home') {
    setTimeout(pop3_status_update, 100);
}
else if (hm_page_name == 'settings') {
    expand_pop3_settings();
}
