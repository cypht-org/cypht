/* globals Hm_Ajax,Hm_Message_List,Hm_Utils,Hm_Folders,Hm_Background_Unread,hm_list_path,hm_msg_uid,hm_search_terms,hm_list_parent,hm_page_name,Message_List,Hm_Timer: true */

var pop3_test_action = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function() { },
        {'pop3_connect': 1}
    );
};

var pop3_save_action = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
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
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
            }
        },
        {'pop3_save': 1}
    );
};

var pop3_forget_action = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
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
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
            }
        },
        {'pop3_forget': 1}
    );
};

var pop3_delete_action = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
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
        {'pop3_delete': 1}
    );
};

var display_pop3_mailbox = function(res) {
    var ids = [res.pop3_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'pop3');
    var key = 'pop3_'+res.pop3_server_id;
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    Hm_Utils.save_to_local_storage(key, data.html());
};

var load_pop3_list = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_folder_display'},
        {'name': 'pop3_server_id', 'value': id}],
        display_pop3_mailbox
    );
    return false;
};

var pop3_message_view = function() {
    $('.msg_text_inner').html('');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_message_display'},
        {'name': 'pop3_list_path', 'value': hm_list_path()},
        {'name': 'pop3_uid', 'value': hm_msg_uid()}],
        display_pop3_message
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
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'pop3');
    if (detail) {
        var class_name = 'pop3_'+detail.server_id+'_'+hm_msg_uid();
        if (hm_list_parent() == 'combined_inbox') {
            Hm_Message_List.prev_next_links('formatted_combined_inbox', class_name);
        }
        else if (hm_list_parent() == 'unread') {
            Hm_Message_List.prev_next_links('formatted_unread_data', class_name);
        }
    }
    $('.header_toggle').click(function() { return Hm_Utils.toggle_long_headers(); });
    $('.msg_part_link').click(function() { return get_message_content($(this).data('messagePart')); });
};

var pop3_all_mail_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_folder_display'},
        {'name': 'pop3_server_id', 'value': id}],
        display_pop3_list,
        [],
        false,
        Hm_Message_List.set_all_mail_state
    );
    return false;
};

var pop3_combined_inbox_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_folder_display'},
        {'name': 'pop3_server_id', 'value': id}],
        display_pop3_list,
        [],
        false,
        Hm_Message_List.set_combined_inbox_state
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
                    update_pop3_status_display
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
            [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_folder_display'},
            {'name': 'pop3_search', 'value': 1},
            {'name': 'pop3_server_id', 'value': id}],
            update_pop3_search_result,
            [],
            false,
            Hm_Message_List.set_search_state
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
        [{'name': 'hm_ajax_hook', 'value': 'ajax_pop3_folder_display'},
        {'name': 'pop3_unread_only', 'value': 1},
        {'name': 'pop3_server_id', 'value': id}],
        update_pop3_unread_display,
        [],
        false,
        Hm_Message_List.set_unread_state
    );
    return false;
};

var update_pop3_unread_display = function(res) {
    var ids = [res.pop3_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'pop3');
};

var expand_pop3_settings = function() {
    var hash = window.location.hash;
    if (hash) {
        if (hash.replace('#', '.') == '.pop3_setting') {
            $('.pop3_setting').css('display', 'table-row');
        }
    }
    else {
        var dsp = Hm_Utils.get_from_local_storage('.pop3_setting');
        if (dsp == 'table-row' || dsp == 'none') {
            $('.pop3_setting').css('display', dsp);
        }
    }
};

if (hm_page_name() == 'servers') {
    $('.test_pop3_connect').on('click', pop3_test_action);
    $('.save_pop3_connection').on('click', pop3_save_action);
    $('.forget_pop3_connection').on('click', pop3_forget_action);
    $('.delete_pop3_connection').on('click', pop3_delete_action);
    var dsp = Hm_Utils.get_from_local_storage('.pop3_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.pop3_section').css('display', dsp);
    }
}
else if (hm_page_name() == 'message' && hm_list_path().substr(0, 4) == 'pop3') {
    pop3_message_view();
}
else if (hm_page_name() == 'info') {
    setTimeout(pop3_status_update, 100);
}
else if (hm_page_name() == 'settings') {
    expand_pop3_settings();
}
