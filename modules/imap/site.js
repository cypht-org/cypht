var imap_delete_action = function() {
    $('.imap_debug_data').empty();
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
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
            Hm_Notices.show(res.router_user_msgs);
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
            Hm_Notices.show(res.router_user_msgs);
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
    event.preventDefault();
    var form = $(this).parent();
    var id = form.find('#imap_server_id');
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            Hm_Notices.show(res.router_user_msgs);
        },
        {'imap_connect': 1}
    );
};

var update_unread_message_display = function(res) {
    if (res.imap_unread_unchanged) {
        console.log("Nothing to update");
        return;
    }
    var count = 0;
    var empty = $('.empty_table', $(res.formatted_unread_data));
    if (empty.length == 0) {
        count = $('tr', $(res.formatted_unread_data)).length - 1;
    }
    if (count < 0) {
        count = 0;
    }
    var title = document.title;
    if (title.search(/\(\d+\)/) != -1) {
        title = title.replace(/\(\d+\)/, ' ('+count+')');
    }
    else {
        title = title + ' ('+count+')';
    }
    document.title = title;
    $('.unread_messages').html(res.formatted_unread_data);
    if (count > 1) {
        $('table', $('.unread_messages')).tablesorter();
    }
};

var imap_unread_update = function(loading) {
    var ids = $('#imap_server_ids').val();
    if ( ids && ids.length ) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_unread'},
            {'name': 'imap_server_ids', 'value': ids}],
            update_unread_message_display,
            [],
            loading
        );
    }
};

var imap_folder_update = function() {
    var ids = $('#imap_server_ids').val();
    if ( ids && ids.length ) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_hm_folders'},
            {'name': 'imap_folder_ids', 'value': ids}],
            update_imap_folder_display,
            [],
            false
        );
    }
    return false;
};

var update_imap_folder_display = function(res) {
    if (res.imap_folders) {
        $('.imap_folders').html(res.imap_folders);
    }
};

var display_msg_text = function(res) {
    if (res.msg_text) {
        var msg_text = $('#msg_text_' + res.msg_text_uid);
        msg_text.html(res.msg_text);
        msg_text.slideDown();
        msg_text.parent().parent().css({'background-color': '#f5f5f5'});
        msg_text.parent().parent().children().css({'border-top': 'solid 1px #ccc'});
        $('body').on('click', function() {
            $('.msg_text').slideUp();
            $('.msg_text').parent().parent().css('background-color', '#fff');
            $('.msg_text').parent().parent().children().css('border-top', 'none');
        } );
    }
};

var msg_preview = function(uid, server_id, folder) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_msg_text'},
        {'name': 'imap_msg_uid', 'value': uid},
        {'name': 'folder', 'value': folder},
        {'name': 'imap_server_id', 'value': server_id}],
        display_msg_text,
        [],
        false
    );
    return false;
};

var parse_folder_path = function(path) {
    var type = false;
    var server_id = false;
    var folder = false;
    parts = path.split(':');
    if (parts.length == 3) {
        type = parts[0];
        server_id = parts[1];
        folder = parts[2];
    }
    if (type && server_id && folder) {
        return {'type': type, 'server_id' : server_id, 'folder' : folder}
    }
    return false;
};

var select_imap_folder = function(path) {
    var detail = parse_folder_path(path);
    if (detail) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_display'},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            display_imap_mailbox,
            [],
            false
        );
    }
    return false;
};

var display_imap_mailbox = function(res) {
    $('.content_cell').html(res.formatted_mailbox_page);
};

var expand_imap_folders = function(path) {
    var detail = parse_folder_path(path);
    var list = $('.imap_'+detail.server_id+'_'+detail.folder.replace(/(:|\.|\[|\])/g, "\\$1"));
    var link = $('a:first-child', list);
    var sublist = $('ul', list);
    console.log(sublist);
    if (link.html() == '+') {
        if (detail) {
            link.html('-');
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                expand_imap_mailbox,
                [],
                false
            );
        }
    }
    else {
        sublist.remove();
        link.html('+');
    }
    return false;
};

var expand_imap_mailbox = function(res) {
    $('.'+res.imap_expanded_folder_path.replace(/(:|\.|\[|\])/g, "\\$1")).append(res.imap_expanded_folder_data);
};

if (hm_page_name == 'home') {
    var imap_folders = $('.imap_folders').html();
    if (!imap_folders) {
        imap_folder_update();
    }
}
else if (hm_page_name == 'servers') {
    $('.imap_delete').on('click', imap_delete_action);
    $('.save_imap_connection').on('click', imap_save_action);
    $('.forget_imap_connection').on('click', imap_forget_action);
    $('.test_imap_connect').on('click', imap_test_action);
}
else if (hm_page_name == 'unread') {
    var content = $('.loading_messages');
    if (content.length > 0) {
        imap_unread_update(true);
    }
    else {
        imap_unread_update();
    }
    Hm_Timer.add_job(imap_unread_update, 60, true);
}

