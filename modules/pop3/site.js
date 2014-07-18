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
    Hm_Notices.hide(true);
    var pop3_id = res.pop3_server_id;
    var msg_ids = [];
    for (index in res.formatted_mailbox_page) {
        row = res.formatted_mailbox_page[index][0];
        id = res.formatted_mailbox_page[index][1];
        if (!$('.'+id).length) {
            $('.message_table tbody').append(row);
            $('.'+id).fadeIn(600);
        }
        msg_ids.push(id);
    }
    $('.message_table tbody tr[class^=pop3_'+pop3_id+'_]').filter(function() {
        var id = this.className;
        if (jQuery.inArray(id, msg_ids) == -1) {
            $(this).fadeOut(600, function() { $('.'+id).remove(); });
        }
    });
    if (res.pop3_page_links) {
        $('.pop3_page_links').html(res.pop3_page_links);
    }
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
};

var add_pop3_sources = function() {
    if ($('.pop3_server_ids').length) {
        var ids = $('.pop3_server_ids').val().split(',');
        if (ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Message_List.sources.push({type: 'pop3', id: id, callback: pop3_combined_inbox_content});
            }
        }
    }
};
var pop3_combined_inbox_content= function(id) {
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


if (hm_page_name == 'servers') {
    $('.test_pop3_connect').on('click', pop3_test_action);
    $('.save_pop3_connection').on('click', pop3_save_action);
    $('.forget_pop3_connection').on('click', pop3_forget_action);
    $('.delete_pop3_connection').on('click', pop3_delete_action);
}
else if (hm_page_name == 'message_list') {
    if (hm_list_path == 'combined_inbox') {
        add_pop3_sources();
    }
    if (hm_list_path.substring(0, 4) == 'pop3') {
        if ($('.message_table tbody tr').length == 0) {
            var detail = parse_folder_path(hm_list_path, 'pop3');
            if (detail) {
                Hm_Message_List.sources.push({type: 'pop3', id: detail.server_id, callback: load_pop3_list});
            }
            Hm_Message_List.load_sources();
            
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
