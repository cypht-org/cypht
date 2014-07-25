var pop3_test_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
        },
        {'pop3_connect': 1}
    );
};

var pop3_save_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.just_saved_credentials) {
                form.find('.credentials').attr('disabled', true);
                form.find('.save_pop3_connection').hide();
                form.find('.pop3_password').val('');
                form.find('.pop3_password').attr('placeholder', '[saved]');
                form.append('<input type="submit" value="Forget" class="forget_pop3_connection" />');
                $('.forget_pop3_connection').on('click', pop3_forget_action);
            }
        },
        {'pop3_save': 1}
    );
};

var pop3_forget_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.just_forgot_credentials) {
                form.find('.credentials').attr('disabled', false);
                form.find('.pop3_password').val('');
                form.find('.pop3_password').attr('placeholder', 'Password');
                form.append('<input type="submit" value="Save" class="save_pop3_connection" />');
                $('.save_pop3_connection').on('click', pop3_save_action);
                $('.forget_pop3_connection', form).remove();
            }
        },
        {'pop3_forget': 1}
    );
};

var pop3_delete_action = function() {
    $('.pop3_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#pop3_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
            if (res.deleted_server_id > -1 ) {
                form.parent().remove();
            }
        },
        {'pop3_delete': 1}
    );
};

var display_pop3_mailbox = function(res) {
    ids = [res.pop3_server_id];
    var count = Hm_Message_List.update(ids, res.formatted_mailbox_page, 'pop3');
    key = 'pop3_'+res.pop3_server_id;
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage(key, data.html());

};

var load_pop3_list = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_folder_display'},
        {'name': 'pop3_server_id', 'value': detail.server_id}],
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
    $('.msg_text').append(res.msg_gravatar);
    $('.msg_text').append(res.msg_headers);
    $('.msg_text').append(res.msg_text);
    set_message_content();
    document.title = 'HM3 '+$('.header_subject th').text();
    pop3_message_view_finished();
};

var pop3_message_view_finished = function() {
    detail = parse_folder_path(hm_list_path, 'pop3');
    if (detail) {
        class_name = 'pop3_'+detail.server_id+'_'+hm_msg_uid;
        if (hm_list_parent == 'combined_inbox') {
            prev_next_links('formatted_combined_inbox', class_name);
        }
        else if (hm_list_parent == 'unread') {
            prev_next_links('formatted_unread_data', class_name);
            update_unread_cache(class_name);
        }
    }
};

var add_pop3_sources = function(callback) {
    if ($('.pop3_server_ids').length) {
        var ids = $('.pop3_server_ids').val().split(',');
        if (ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Message_List.sources.push({type: 'pop3', id: id, callback: callback});
            }
        }
    }
};
var pop3_combined_inbox_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_combined_inbox'},
        {'name': 'limit', 'value': 10},
        {'name': 'pop3_server_id', 'value': id}],
        display_pop3_combined_inbox,
        [],
        false,
        set_combined_inbox_state
    );
    return false;
};

var display_pop3_combined_inbox = function(res) {
    var ids = [res.pop3_server_id];
    var count = Hm_Message_List.update(ids, res.formatted_mailbox_page, 'pop3');
};

var pop3_status_update = function() {
    if ($('.pop3_server_ids').length) {
        var ids = $('.pop3_server_ids').val().split(',');
        if ( ids && ids != '') {
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

var pop3_combined_unread_content = function(id) {
    var since = 'today';
    if ($('.message_list_since').length) {
        since = $('.message_list_since option:selected').val();
    }
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_unread'},
        {'name': 'unread_since', 'value': since},
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
    var count = Hm_Message_List.update(ids, res.formatted_mailbox_page, 'pop3');
};


if (hm_page_name == 'servers') {
    $('.test_pop3_connect').on('click', pop3_test_action);
    $('.save_pop3_connection').on('click', pop3_save_action);
    $('.forget_pop3_connection').on('click', pop3_forget_action);
    $('.delete_pop3_connection').on('click', pop3_delete_action);
}
else if (hm_page_name == 'message_list') {
    if (hm_list_path == 'combined_inbox') {
        add_pop3_sources(pop3_combined_inbox_content);
    }
    else if (hm_list_path == 'unread') {
        add_pop3_sources(pop3_combined_unread_content);
    }
    else if (hm_list_path.substring(0, 4) == 'pop3') {
        if ($('.message_table tbody tr').length == 0) {
            var detail = parse_folder_path(hm_list_path, 'pop3');
            if (detail) {
                Hm_Message_List.sources.push({type: 'pop3', id: detail.server_id, callback: load_pop3_list});
            }
            Hm_Message_List.setup_combined_view(hm_list_path);
            
        }
        $('.message_table tr').fadeIn(100);
    }
}
else if (hm_page_name == 'message' && hm_list_path.substr(0, 4) == 'pop3') {
    pop3_message_view();
}
else if (hm_page_name == 'home') {
    pop3_status_update();
}
