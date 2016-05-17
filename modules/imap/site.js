'use strict'

var imap_delete_action = function(event) {
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
        {'imap_delete': 1}
    );
};

var imap_hide_action = function(form, server_id, hide) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_debug'},
        {'name': 'imap_server_id', 'value': server_id},
        {'name': 'hide_imap_server', 'value': hide}],
        function() {
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

var imap_hide = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
    var form = $(this).parent();
    var server_id = $('.imap_server_id', form).val();
    imap_hide_action(form, server_id, 1);
};

var imap_unhide = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
    var form = $(this).parent();
    var server_id = $('.imap_server_id', form).val();
    imap_hide_action(form, server_id, 0);
};

var imap_forget_action = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
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

var imap_save_action = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
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

var imap_test_action = function(event) {
    $('.imap_folder_data').empty();
    event.preventDefault();
    Hm_Notices.hide(true);
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        false,
        {'imap_connect': 1}
    );
}

var imap_setup_server_page = function() {
    $('.imap_delete').on('click', imap_delete_action);
    $('.save_imap_connection').on('click', imap_save_action);
    $('.hide_imap_connection').on('click', imap_hide);
    $('.unhide_imap_connection').on('click', imap_unhide);
    $('.forget_imap_connection').on('click', imap_forget_action);
    $('.test_imap_connect').on('click', imap_test_action);
    var dsp = Hm_Utils.get_from_local_storage('.imap_section');
    if (dsp === 'block' || dsp === 'none') {
        $('.imap_section').css('display', dsp);
    }
};

var set_message_content = function(path, msg_uid) {
    if (!path) {
        path = hm_list_path();
    }
    if (!msg_uid) {
        msg_uid = hm_msg_uid();
    }
    var key = msg_uid+'_'+path;
    Hm_Utils.save_to_local_storage(key, $('.msg_text').html());
};

var imap_delete_message = function(state) {
    var uid = hm_msg_uid();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (detail && uid) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_delete_message'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function(res) {
                if (!res.imap_delete_error) {
                    var nlink = $('.nlink');
                    if (nlink.length) {
                        window.location.href = nlink.attr('href');
                    }
                    else {
                        window.location.href = "?page=message_list&list_path="+hm_list_parent();
                    }
                }
            }
        );
    }
    return false;
};

var imap_flag_message = function(state) {
    var uid = hm_msg_uid();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (detail && uid) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_flag_message'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_flag_state', 'value': state},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function() {
                if (state === 'flagged') {
                    $('#flag_msg').show();
                    $('#unflag_msg').hide();
                }
                else {
                    $('#flag_msg').hide();
                    $('#unflag_msg').show();
                }
                $('.next').remove();
                $('.prev').remove();
                set_message_content();
                imap_message_view_finished();
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

var imap_sent_content = function(id, folder) {
    return imap_message_list_content(id, folder, 'ajax_imap_sent', cache_sent_data);
};

var cache_sent_data = function() {
    if (hm_list_path() == 'sent') {
        Hm_Message_List.set_message_list_state('formatted_sent_data');
    }
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

var update_imap_combined_source = function(path, state, event) {
    event.preventDefault();
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_update_combined_source'},
        {'name': 'list_path', 'value': path},
        {'name': 'combined_source_state', 'value': state}],
        function() {
            if (state === 1) {
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

var remove_imap_combined_source = function(event) {
    return update_imap_combined_source(hm_list_path(), 0, event);
};

var add_imap_combined_source = function(event) {
    return update_imap_combined_source(hm_list_path(), 1, event);
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

var get_imap_page_number = function() {
    var index;
    var match_result;
    var page_number = 1;
    var params = location.search.substr(1).split('&');
    var param_len = params.length;

    for (index=0; index < param_len; index++) {
        match_result = params[index].match(/list_page=(\d+)/);
        if (match_result) {
            page_number = match_result[1];
            break;
        }
    }
    return page_number;
}

var cache_imap_page = function() {
    var key = 'imap_'+get_imap_page_number()+'_'+hm_list_path();
    Hm_Utils.save_to_local_storage(key, $('.message_table tbody').html());
    Hm_Utils.save_to_local_storage(key+'_page_links', $('.page_links').html());
}

var fetch_cached_imap_page = function() {
    var key = 'imap_'+get_imap_page_number()+'_'+hm_list_path();
    var page = Hm_Utils.get_from_local_storage(key);
    var links = Hm_Utils.get_from_local_storage(key+'_page_links');
    return [ page, links ];

}

var select_imap_folder = function(path, callback) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    if (detail) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_display'},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            display_imap_mailbox,
            [],
            false,
            callback
        );
    }
    return false;
};

var setup_imap_folder_page = function() {
    var cache_details = fetch_cached_imap_page();
    if (cache_details[0]) {
        $('.message_table tbody').html(cache_details[0]);
    }
    if (cache_details[1]) {
        $('.page_links').html(cache_details[1]);
    }
    select_imap_folder(hm_list_path());
    $('.remove_source').click(remove_imap_combined_source);
    $('.add_source').click(add_imap_combined_source);
    $('.refresh_link').click(function() { select_imap_folder(hm_list_path()); });
};

var display_imap_mailbox = function(res) {
    var ids = [res.imap_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'imap');
    Hm_Message_List.check_empty_list();
    if (res.page_links) {
        $('.page_links').html(res.page_links);
    }
    $('input[type=checkbox]').click(function(e) {
        Hm_Message_List.toggle_msg_controls();
        Hm_Message_List.check_select_range(e);
    });
    cache_imap_page();
};

var expand_imap_mailbox = function(res) {
    if (res.imap_expanded_folder_path) {
        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), $('.email_folders')).append(res.imap_expanded_folder_formatted);
        $('.imap_folder_link', $('.email_folders')).unbind('click');
        $('.imap_folder_link', $('.email_folders')).click(function() { return expand_imap_folders($(this).data('target')); });
    }
};

var expand_imap_folders = function(path) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+Hm_Utils.clean_selector(detail.folder), $('.email_folders'));
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

var get_message_content = function(msg_part, uid, list_path, detail, callback) {
    if (!uid) {
        uid = $('.msg_uid').val();
    }
    if (!detail) {
        detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    }
    if (detail && uid) {
        window.scrollTo(0,0);
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_content'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_msg_part', 'value': msg_part},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function(res) {
                $('.msg_text').html('');
                $('.msg_text').append(res.msg_headers);
                $('.msg_text').append(res.msg_text);
                $('.msg_text').append(res.msg_parts);
                set_message_content(list_path, uid);
                document.title = $('.header_subject th').text();
                imap_message_view_finished();
            },
            [],
            false,
            callback
        );
    }
    return false;
};

var imap_mark_as_read = function(uid, detail) {
    if (!uid) {
        uid = $('.msg_uid').val();
    }
    if (!detail) {
        detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    }
    if (detail && uid) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_mark_as_read'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function() {},
            false,
            true
        );
    }
    return false;
};

var imap_message_view_finished = function(msg_uid, detail) {
    var class_name = false;
    if (!detail) {
        detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    }
    if (!msg_uid) {
        msg_uid = hm_msg_uid();
    }
    if (detail) {
        class_name = 'imap_'+detail.server_id+'_'+msg_uid+'_'+detail.folder;
        if (hm_list_parent() === 'combined_inbox') {
            Hm_Message_List.prev_next_links('formatted_combined_inbox', class_name);
        }
        else if (hm_list_parent() === 'unread') {
            Hm_Message_List.prev_next_links('formatted_unread_data', class_name);
        }
        else if (hm_list_parent() === 'flagged') {
            Hm_Message_List.prev_next_links('formatted_flagged_data', class_name);
        }
        else {
            var key = 'imap_'+get_imap_page_number()+'_'+hm_list_path();
            Hm_Message_List.prev_next_links(key, class_name);
        }
    }
    if (class_name) {
        Hm_Message_List.track_read_messages(class_name);
    }
    $('.header_toggle').click(function() { return Hm_Utils.toggle_long_headers(); });
    $('.msg_part_link').click(function() { return get_message_content($(this).data('messagePart')); });
    $('#flag_msg').click(function() { return imap_flag_message($(this).data('state')); });
    $('#unflag_msg').click(function() { return imap_flag_message($(this).data('state')); });
    $('#delete_message').click(function() { return imap_delete_message(); });
    $('#move_message').click(function() { return imap_move_copy('move');});
    $('#copy_message').click(function() { return imap_move_copy('copy');});
};

var get_local_message_content = function(msg_uid, path) {
    if (!path) {
        path = hm_list_path();
    }
    if (!msg_uid) {
        msg_uid = hm_msg_uid();
    }
    var key = msg_uid+'_'+path;
    return Hm_Utils.get_from_local_storage(key);
};

var imap_prefetch_message_content = function(uid, server_id, folder) {
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
                var div;
                div = $('<div></div>');
                div.append(res.msg_headers);
                div.append(res.msg_text);
                div.append(res.msg_parts);
                Hm_Utils.save_to_local_storage(key, div.html());
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

var imap_setup_message_view_page = function(uid, details, list_path, callback) {
    var msg_content = get_local_message_content(uid, list_path);
    if (!msg_content || !msg_content.length || msg_content.indexOf('<div class="msg_text_inner"></div>') > -1) {
        get_message_content(false, uid, list_path, details, callback);
    }
    else {
        $('.msg_text').html(msg_content);
        document.title = $('.header_subject th').text();
        imap_message_view_finished(uid, details);
        imap_mark_as_read(uid, details);
        if (callback) {
            callback();
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

var imap_background_unread_content_result = function(res) {
    var ids = [res.imap_server_id];
    var cache = $('<tbody></tbody>').append($(Hm_Utils.get_from_local_storage('formatted_unread_data')));
    var count = $('tr', cache).length;
    globals.Hm_Background_Unread.update(ids, res.formatted_message_list, 'imap', cache);
    Hm_Utils.save_to_local_storage('formatted_unread_data', cache.html());
    if ($('tr', cache).length > count) {
        $('.menu_unread > a').css('font-weight', 'bold');
        Hm_Folders.save_folder_list();
    }
};


var check_select_for_imap = function() {
    $('input[type=checkbox]').unbind('change'); 
    $('input[type=checkbox]').change(function(e) { search_selected_for_imap(); });
};

var search_selected_for_imap = function() {
    var imap_selected = false;
    $('input[type=checkbox]').each(function() {
        if (this.checked && this.id.search('imap') != -1) {
            imap_selected = true;
            return false;
        }
    });
    if (imap_selected) {
        $('.imap_move').removeClass('disabled_input');
        $('.imap_move').unbind('click');
        $('.imap_move').click(function(e) {return imap_move_copy($(this).data('action'));});
    }
    else {
        $('.imap_move').addClass('disabled_input');
        $('.imap_move').unbind('click');
        $('.imap_move').click(function() { return false; });
        $('.move_to_location').html('');
        $('.move_to_location').hide();
    }
};

var unselect_non_imap_messages = function() {
    var unselected = 0;
    $('input[type=checkbox]').each(function() {
        if (this.checked && this.id.search('imap') == -1) {
            this.checked = false;
            unselected++;
        }
    });
    if (unselected > 0) {
        Hm_Notices.show({0: 'ERR'+$('.move_to_string3').val()});
    }
};

var imap_move_copy = function(action) {
    unselect_non_imap_messages();
    var label;
    var move_to = $('.move_to_location');
    var folders = $('.email_folders').clone(false);
    $('.menu_email', folders).remove();
    folders.removeClass('email_folders');
    folders.show();
    $('.imap_folder_link', folders).addClass('imap_move_folder_link').removeClass('imap_folder_link');
    if (action == 'move') {
        label = $('.move_to_string1').val(); 
    }
    else {
        label = $('.move_to_string2').val();
    }
    folders.prepend('<div class="move_to_title">'+label+'<span><a class="close_move_to" href="#">X</a></span></div>');
    move_to.html(folders);
    $('.imap_move_folder_link', move_to).click(function() { return expand_imap_move_to_folders($(this).data('target')); });
    $('a', move_to).not('.imap_move_folder_link').not('.close_move_to').unbind('click');
    $('a', move_to).not('.imap_move_folder_link').not('.close_move_to').click(function() { imap_perform_move_copy($(this).data('id')); return false; });
    $('.move_to_type').val(action);
    $('.close_move_to').click(function() {
        $('.move_to_location').html('');
        $('.move_to_location').hide();
        return false;
    });
    move_to.show();
    return false;
};

var imap_perform_move_copy = function(dest_id) {
    var action = $('.move_to_type').val();
    var ids = [];
    var page = hm_page_name();
    $('.move_to_location').html('');
    $('.move_to_location').hide();
    if (page == 'message') {
        var uid = hm_msg_uid();
        var path = Hm_Utils.parse_folder_path(hm_list_path());
        ids.push('imap_'+path['server_id']+'_'+uid+'_'+path['folder']);
    }
    else {
        $('input[type=checkbox]').each(function() {
            if (this.checked && this.id.search('imap') != -1) {
                ids.push(this.id);
            }
        });
    }
    if (ids.length > 0 && dest_id) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_move_copy_action'},
            {'name': 'imap_move_ids', 'value': ids.join(',')},
            {'name': 'imap_move_to', 'value': dest_id},
            {'name': 'imap_move_page', 'value': page},
            {'name': 'imap_move_action', 'value': action}],
            function(res) {
                var index;
                if (hm_page_name() == 'message_list') {
                    Hm_Message_List.reset_checkboxes();
                    if (action == 'move') {
                        for (index in res.move_count) {
                            $('.'+Hm_Utils.clean_selector(res.move_count[index])).remove();
                        }
                    }
                    if (hm_list_path().substr(0, 4) === 'imap') {
                        select_imap_folder(hm_list_path());
                    }
                    else {
                        Hm_Message_List.load_sources();
                    }
                }
                else {
                    if (action == 'move') {
                        var nlink = $('.nlink');
                        if (nlink.length) {
                            window.location.href = nlink.attr('href');
                        }
                        else {
                            window.location.href = "?page=message_list&list_path="+hm_list_parent();
                        }
                    }
                }
            }
        );
    }
};

var expand_imap_move_to_mailbox = function(res) {
    if (res.imap_expanded_folder_path) {
        var move_to = $('.move_to_location');
        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), $('.move_to_location')).append(res.imap_expanded_folder_formatted);
        $('.imap_folder_link', move_to).addClass('imap_move_folder_link').removeClass('imap_folder_link');
        $('.imap_move_folder_link', move_to).unbind('click');
        $('.imap_move_folder_link', move_to).click(function() { return expand_imap_move_to_folders($(this).data('target')); });
        $('a', move_to).not('.imap_move_folder_link').unbind('click');
        $('a', move_to).not('.imap_move_folder_link').click(function() { imap_perform_move_copy($(this).data('id')); return false; });
    }
};

var expand_imap_move_to_folders = function(path) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+Hm_Utils.clean_selector(detail.folder), $('.move_to_location'));
    if ($('li', list).length === 0) {
        $('.expand_link', list).html('-');
        if (detail) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                expand_imap_move_to_mailbox
            );
        }
    }
    else {
        $('.expand_link', list).html('+');
        $('ul', list).remove();
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

if (hm_page_name() === 'message_list' && hm_list_path().substr(0, 4) === 'imap') {
    setup_imap_folder_page();
}
else if (hm_page_name() === 'message' && hm_list_path().substr(0, 4) === 'imap') {
    imap_setup_message_view_page();
}
else if (hm_page_name() === 'servers') {
    imap_setup_server_page();
}
else if (hm_page_name() === 'info') {
    setTimeout(imap_status_update, 100);
}
else if (hm_list_path() == 'sent') {
    Hm_Message_List.page_caches.sent = 'formatted_sent_data';
}

$(function() {
    if ($('.imap_move').length > 0) {
        check_select_for_imap();
        $('.toggle_link').click(function() { setTimeout(search_selected_for_imap, 100); });
        Hm_Ajax.add_callback_hook('ajax_imap_folder_display', check_select_for_imap);
        Hm_Message_List.callbacks.push(check_select_for_imap);
        $('.imap_move').click(function() { return false; });
    }

    if (hm_list_path() !== 'unread') {
        if (typeof hm_data_sources_background === 'function') {
            globals.Hm_Background_Unread = new Message_List();
            globals.Hm_Background_Unread.add_sources(hm_data_sources_background());
            Hm_Timer.add_job(globals.Hm_Background_Unread.load_sources, 43, true);
        }
    }
    Hm_Timer.add_job(imap_prefetch_msgs, 83, true);
});
