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
    var ids = res.unread_server_ids.split(',');
    var msg_ids = [];
    for (index in res.formatted_unread_data) {
        row = res.formatted_unread_data[index][0];
        id = res.formatted_unread_data[index][1];
        if (!$('.'+id).length) {
            $('.message_table tbody').prepend(row);
            $('.'+id).fadeIn(600);
        }
        msg_ids.push(id);
    }
    for (i=0;i<ids.length;i++) {
        $('.message_table tbody tr[class^=imap_'+ids[i]+'_]').filter(function() {
            var id = this.className;
            if (jQuery.inArray(id, msg_ids) == -1) {
                $(this).fadeOut(600, function() { $('.'+id).remove(); });
            }
        });
    }
};

var imap_unread_update = function(loading) {
    var ids = $('#imap_server_ids').val().split(',');
    if ( ids && ids != '') {
        Hm_Notices.show({0: 'Updating unread messages ...'});
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

var close_msg_preview = function() {
    $('.overlay').remove();
    $('.msg_text').slideUp();
    return false;
};

var toggle_long_headers = function() {
    $('.long_header').toggle(600);
    $('.header_toggle').toggle(600);
    return false;
};

var display_msg_text = function(res) {
    Hm_Notices.hide(true);
    overlay = $('<div></div>').prependTo('body').attr('class', 'overlay');
    $('.overlay').on('click', close_msg_preview);
    if (res.msg_text) {
        var msg_text = $('.msg_text');
        msg_text.html(res.msg_text);
        msg_text.slideDown();
    }
};

var msg_preview = function(uid, server_id, folder) {
    Hm_Notices.show({0: 'Fetching message text ...'});
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

var select_imap_folder = function(path, force) {
    var detail = parse_folder_path(path, 'imap');
    if (detail) {
        if (force) {
            Hm_Notices.show({0: 'Updating folder...'});
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
        if (!$('.'+id).length) {
            $('.message_table tbody').append(row);
            $('.'+id).fadeIn(600);
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

    /*$('.message_table').tablesorter({debug: true, sortList: [[3,1],[2,0]]});*/
};

var expand_imap_folders = function(path) {
    var detail = parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+clean_selector(detail.folder));
    var link = $('a:first-child', list);
    var sublist = $('ul', list);
    if (link.html() == '+') {
        if (detail) {
            link.html('-');
            Hm_Notices.show({0: 'Loading subfolder ...'});
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
        sublist.remove();
        link.html('+');
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
        false
    );
};

var expand_imap_mailbox = function(res) {
    $('.'+clean_selector(res.imap_expanded_folder_path)).append(res.imap_expanded_folder_formatted);
};

var set_unread_state = function() {
    $('.message_table').tablesorter({debug: true, headers: { 3: { sorter: 'dt' } }, sortList: [[3,1],[2,0]]});
    var data = $('.message_table tbody').html();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_save_unread_state'},
        {'name': 'formatted_unread_data', 'value': data}],
        false,
        [],
        false
    );
};

if (hm_page_name == 'message_list') {
    if (hm_list_path == 'unread') {
        $('.menu_unread').addClass('selected_menu');
        Hm_Timer.add_job(imap_unread_update, 60);
        $('.message_table tr').fadeIn(100);
    }
    else if (hm_list_path.substring(0, 4) == 'imap') {
        $('a:eq(0)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
        $('a:eq(1)', $('.'+clean_selector(hm_list_path))).addClass('selected_menu');
        if ($('.message_table tbody tr').length == 0) {
            select_imap_folder(hm_list_path, true);
        }
        $('.message_table tr').fadeIn(100);
    }
}
else if (hm_page_name == 'servers') {
    $('.imap_delete').on('click', imap_delete_action);
    $('.save_imap_connection').on('click', imap_save_action);
    $('.forget_imap_connection').on('click', imap_forget_action);
    $('.test_imap_connect').on('click', imap_test_action);
}
if ($.tablesorter) {
    $.tablesorter.addParser({ 
        id: 'dt', 
        is: function(s) { return false; }, 
        format: function(s) { return Date.parse(s); }, 
        type: 'numeric' 
    }); 
}
