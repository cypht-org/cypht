'use strict';

var imap_delete_action = function(event) {
    if (!hm_delete_prompt()) {
        return false;
    }
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
                form.find('.credentials').val('');
                form.find('.credentials').attr('placeholder', '');
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
    var jdsp = Hm_Utils.get_from_local_storage('.jmap_section');
    if (jdsp === 'block' || jdsp === 'none') {
        $('.jmap_section').css('display', jdsp);
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

var imap_delete_message = function(state, supplied_uid, supplied_detail) {
    if (!hm_delete_prompt()) {
        return false;
    }
    var uid = hm_msg_uid();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (supplied_uid) {
        uid = supplied_uid;
    }
    if (supplied_detail) {
        detail = supplied_detail;
    }
    if (detail && uid) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_delete_message'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function(res) {
                if (!res.imap_delete_error) {
                    if (Hm_Utils.get_from_global('msg_uid', false)) {
                        return;
                    }
                    var nlink = $('.nlink');
                    if (nlink.length) {
                        window.location.href = nlink.attr('href');
                    }
                    else {
                        if (!hm_list_parent()) {
                            window.location.href = "?page=message_list&list_path="+hm_list_path();
                        }
                        else {
                            window.location.href = "?page=message_list&list_path="+hm_list_parent();
                        }
                    }
                }
            }
        );
    }
    return false;
};

var imap_unread_message = function(supplied_uid, supplied_detail) {
    var uid = hm_msg_uid();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (supplied_uid) {
        uid = supplied_uid;
    }
    if (supplied_detail) {
        detail = supplied_detail;
    }
    if (detail && uid) {
        var selected = detail.type+'_'+detail.server_id+'_'+uid+'_'+detail.folder;
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_message_action'},
            {'name': 'action_type', 'value': 'unread'},
            {'name': 'message_ids', 'value': selected}],
            function(res) {
                    if (Hm_Utils.get_from_global('uid', false)) {
                        return;
                    }
                    var nlink = $('.nlink');
                    if (nlink.length) {
                        window.location.href = nlink.attr('href');
                    }
                    else {
                        if (!hm_list_parent()) {
                            window.location.href = "?page=message_list&list_path="+hm_list_path();
                        }
                        else {
                            window.location.href = "?page=message_list&list_path="+hm_list_parent();
                        }
                    }
            },
            [],
            false,
            function() {
                var cache = $('<tbody></tbody>').append($(Hm_Utils.get_from_local_storage('formatted_unread_data')));
                Hm_Message_List.adjust_unread_total($('tr', cache).length, true);
            }
        );
    }
    return false;
}

var imap_flag_message = function(state, supplied_uid, supplied_detail) {
    var uid = hm_msg_uid();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (supplied_uid) {
        uid = supplied_uid;
    }
    if (supplied_detail) {
        detail = supplied_detail;
    }
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
                set_message_content();
                imap_message_view_finished(false, false, true);
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
            if (res.auto_sent_folder) {
                add_auto_folder(res.auto_sent_folder);
            }
            Hm_Message_List.update(ids, res.formatted_message_list, 'imap');
        },
        [],
        false,
        batch_callback
    );
    return false;
};

var add_auto_folder = function(folder) {
    $('.list_sources').append('<div class="list_src">imap '+folder+'</div>');
    var count = $('.src_count').text()*1;
    count++;
    $('.src_count').html(count);
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

var cache_imap_page = function() {
    var key = 'imap_'+Hm_Utils.get_url_page_number()+'_'+hm_list_path();
    var data = Hm_Message_List.filter_list();
    data.find('input[type=checkbox]').removeAttr('checked');
    Hm_Utils.save_to_local_storage(key, data.html());
    Hm_Utils.save_to_local_storage(key+'_page_links', $('.page_links').html());
}

var clear_imap_page_cache = function() {
    var key = 'imap_'+Hm_Utils.get_url_page_number()+'_'+hm_list_path();
    Hm_Utils.save_to_local_storage(key, '');
    Hm_Utils.save_to_local_storage(key+'_page_links', '');
}

var fetch_cached_imap_page = function() {
    var key = 'imap_'+Hm_Utils.get_url_page_number()+'_'+hm_list_path();
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
    Hm_Timer.add_job(function() { select_imap_folder(hm_list_path()); }, 60);
    $('.remove_source').on("click", remove_imap_combined_source);
    $('.add_source').on("click", add_imap_combined_source);
    $('.refresh_link').on("click", function() {
        if ($('.imap_keyword').val()) {
            $('#imap_filter_form').submit();
        }
        else {
            select_imap_folder(hm_list_path());
        }
    });
    $('.imap_filter').on("change", function() { $('#imap_filter_form').submit(); });
    $('.imap_sort').on("change", function() {
        clear_imap_page_cache();
        $('#imap_filter_form').submit();
    });
    $('.imap_keyword').on('search', function() {
        $('#imap_filter_form').submit();
    });
    Hm_Ajax.add_callback_hook('ajax_message_action', function() { select_imap_folder(hm_list_path()); });
};

var display_imap_mailbox = function(res) {
    var ids = [res.imap_server_id];
    Hm_Message_List.update(ids, res.formatted_message_list, 'imap');
    Hm_Message_List.check_empty_list();
    $('.page_links').html(res.page_links);
    $('input[type=checkbox]').on("click", function(e) {
        Hm_Message_List.toggle_msg_controls();
    });
    cache_imap_page();
};

var expand_imap_mailbox = function(res) {
    if (res.imap_expanded_folder_path) {
        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), $('.email_folders')).append(res.imap_expanded_folder_formatted);
        $('.imap_folder_link', $('.email_folders')).off('click');
        $('.imap_folder_link', $('.email_folders')).on("click", function() { return expand_imap_folders($(this).data('target')); });
        Hm_Folders.update_unread_counts();
    }
};

var prefetch_imap_folders = function() {
    var id_el = $('#imap_prefetch_ids');
    if (!id_el.length) {
        return;
    }
    var ids = id_el.val().split(',');
    if (ids.length == 0 ) {
        return;
    }
    var id = ids.shift();
    if (id === '') {
        return;
    }

    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
        {'name': 'imap_server_id', 'value': id},
        {'name': 'imap_prefetch', 'value': true},
        {'name': 'folder', 'value': ''}],
        function(res) { $('#imap_prefetch_ids').val(ids.join(',')); prefetch_imap_folders(); },
        [],
        true
    );

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

var get_message_content = function(msg_part, uid, list_path, detail, callback, noupdate, images) {
    if (!images) {
        images = 0;
    }
    if (!uid) {
        uid = $('.msg_uid').val();
    }
    if (!detail) {
        detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    }
    if (detail && uid) {
        if (hm_page_name() == 'message') {
            window.scrollTo(0,0);
        }
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_message_content'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_msg_part', 'value': msg_part},
            {'name': 'imap_allow_images', 'value': images},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function(res) {
                if (!noupdate) {
                    $('.msg_text').html('');
                    $('.msg_text').append(res.msg_headers);
                    $('.msg_text').append(res.msg_text);
                    $('.msg_text').append(res.msg_parts);
                    set_message_content(list_path, uid);
                    document.title = $('.header_subject th').text();
                    imap_message_view_finished();
                }
                else {
                    $('.reply_link, .reply_all_link, .forward_link').each(function() {
                        $(this).attr("href", $(this).data("href"));
                        $(this).removeClass('disabled_link');
                    });
                }
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

var imap_message_view_finished = function(msg_uid, detail, skip_links) {
    var class_name = false;
    if (!detail) {
        detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    }
    if (!msg_uid) {
        msg_uid = hm_msg_uid();
    }
    if (detail && !skip_links) {
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
        else if (hm_list_parent() === 'advanced_search') {
            Hm_Message_List.prev_next_links('formatted_advanced_search_data', class_name);
        }
        else if (hm_list_parent() === 'search') {
            Hm_Message_List.prev_next_links('formatted_search_data', class_name);
        }
        else if (hm_list_parent() === 'sent') {
            Hm_Message_List.prev_next_links('formatted_sent_data', class_name);
        }
        else {
            var key = 'imap_'+Hm_Utils.get_url_page_number()+'_'+hm_list_path();
            Hm_Message_List.prev_next_links(key, class_name);
        }
    }
    if (Hm_Message_List.track_read_messages(class_name)) {
        if (hm_list_parent() == 'unread') {
            Hm_Message_List.adjust_unread_total(-1);
        }
    }
    $('.all_headers').on("click", function() { return Hm_Utils.toggle_long_headers(); });
    $('.small_headers').on("click", function() { return Hm_Utils.toggle_long_headers(); });
    $('.msg_part_link').on("click", function() {
        $('.header_subject')[0].scrollIntoView();
        $('.msg_text_inner').css('visibility', 'hidden');
        return get_message_content($(this).data('messagePart'), false, false, false, false, false, $(this).data('allowImages'));
    });
    $('#flag_msg').on("click", function() { return imap_flag_message($(this).data('state')); });
    $('#unflag_msg').on("click", function() { return imap_flag_message($(this).data('state')); });
    $('#delete_message').on("click", function() { return imap_delete_message(); });
    $('#move_message').on("click", function(e) { return imap_move_copy(e, 'move', 'message');});
    $('#copy_message').on("click", function(e) { return imap_move_copy(e, 'copy', 'message');});
    $('#archive_message').on("click", function(e) { return imap_archive_message();});
    $('#unread_message').on("click", function() { return inline_imap_unread_message(msg_uid, detail);});
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
        $('.reply_link, .reply_all_link, .forward_link').each(function() {
            $(this).data("href", $(this).attr("href")).removeAttr("href");
            $(this).addClass('disabled_link');
        });
        imap_message_view_finished();
        get_message_content(false, uid, list_path, details, callback, true);
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
    if (!$.isEmptyObject(res.folder_status)) {
        var detail = Hm_Utils.parse_folder_path(Object.keys(res.folder_status)[0], 'imap');
        var ids = [detail.server_id+'_'+detail.folder];
        var cache = $('<tbody></tbody>').append($(Hm_Utils.get_from_local_storage('formatted_unread_data')));
        globals.Hm_Background_Unread.update(ids, res.formatted_message_list, 'imap', cache);
        Hm_Utils.save_to_local_storage('formatted_unread_data', cache.html());
    }
};

var check_select_for_imap = function() {
    $('input[type=checkbox]').off('change'); 
    $('input[type=checkbox]').on("change", function(e) { search_selected_for_imap(); });
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
        $('.imap_move').off('click');
        $('.imap_move').on("click", function(e) {return imap_move_copy(e, $(this).data('action'), 'list');});
    }
    else {
        $('.imap_move').addClass('disabled_input');
        $('.imap_move').off('click');
        $('.imap_move').on("click", function() { return false; });
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

var imap_move_copy = function(e, action, context) {
    var move_to;
    if (!e.target || e.target.className == 'imap_move') {
        move_to = $('.msg_controls .move_to_location');
    }
    else {
        move_to = $('.msg_text .move_to_location');
    }
    unselect_non_imap_messages();
    var label;
    var folders = $('.email_folders').clone(false);
    folders.find('.manage_folders_li').remove();
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
    move_to.html(folders.html());
    $('.imap_move_folder_link', move_to).on("click", function() { return expand_imap_move_to_folders($(this).data('target'), context); });
    $('a', move_to).not('.imap_move_folder_link').not('.close_move_to').off('click');
    $('a', move_to).not('.imap_move_folder_link').not('.close_move_to').on("click", function() { imap_perform_move_copy($(this).data('id'), context); return false; });
    $('.move_to_type').val(action);
    $('.close_move_to').on("click", function() {
        $('.move_to_location').html('');
        $('.move_to_location').hide();
        return false;
    });
    move_to.show();
    return false;
};

var imap_perform_move_copy = function(dest_id, context) {
    var action = $('.move_to_type').val();
    var ids = [];
    var page = hm_page_name();
    $('.move_to_location').html('');
    $('.move_to_location').hide();

    if (context == 'message') {
        var inline_uuid = Hm_Utils.get_from_global('inline_move_uuid', false);
        if (inline_uuid) {
            ids.push(inline_uuid);
            globals['inline_move_uuid'] = false;
        }
        else if (page == 'message') {
            var uid = hm_msg_uid();
            var path = Hm_Utils.parse_folder_path(hm_list_path());
            ids.push('imap_'+path['server_id']+'_'+uid+'_'+path['folder']);
        }
    }
    else if (context == 'list') {
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
                            if(hm_page_name() == 'search'){
                                window.location.reload();
                            }
                            else if(hm_page_name() == 'advanced_search'){
                                process_advanced_search();
                            }else{
                                window.location.href = "?page=message_list&list_path="+hm_list_parent();
                            }
                        }
                    }
                }
            }
        );
    }
};

var expand_imap_move_to_mailbox = function(res, context) {
    if (res.imap_expanded_folder_path) {
        var move_to = $('.move_to_location');
        var folders = $(res.imap_expanded_folder_formatted);
        folders.find('.manage_folders_li').remove();
        $('.'+Hm_Utils.clean_selector(res.imap_expanded_folder_path), $('.move_to_location')).append(folders);
        $('.imap_folder_link', move_to).addClass('imap_move_folder_link').removeClass('imap_folder_link');
        $('.imap_move_folder_link', move_to).off('click');
        $('.imap_move_folder_link', move_to).on("click", function() { return expand_imap_move_to_folders($(this).data('target'), context); });
        $('a', move_to).not('.imap_move_folder_link').off('click');
        $('a', move_to).not('.imap_move_folder_link').on("click", function() { imap_perform_move_copy($(this).data('id'), context); return false; });
    }
};

var expand_imap_move_to_folders = function(path, context) {
    var detail = Hm_Utils.parse_folder_path(path, 'imap');
    var list = $('.imap_'+detail.server_id+'_'+Hm_Utils.clean_selector(detail.folder), $('.move_to_location'));
    if ($('li', list).length === 0) {
        $('.expand_link', list).html('-');
        if (detail) {
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_expand'},
                {'name': 'imap_server_id', 'value': detail.server_id},
                {'name': 'folder', 'value': detail.folder}],
                function (res) { expand_imap_move_to_mailbox(res, context); }
            );
        }
    }
    else {
        $('.expand_link', list).html('+');
        $('ul', list).remove();
    }
    return false;
};

var imap_background_unread_content = function(id, folder) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_unread'},
        {'name': 'folder', 'value': folder},
        {'name': 'imap_server_ids', 'value': id}],
        imap_background_unread_content_result,
        [],
        false,
        function() {
            var cache = $('<tbody></tbody>').append($(Hm_Utils.get_from_local_storage('formatted_unread_data')));
            Hm_Message_List.adjust_unread_total($('tr', cache).length, true);
        }
    );
    return false;
};

var get_imap_folder_status = function(id, folder) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_folder_status'},
        {'name': 'imap_server_id', 'value': id},
        {'name': 'folder', 'value': folder}],
        false,
        [],
        true,
        Hm_Folders.update_unread_counts
    );
}

var imap_folder_status = function() {
    var source;
    var sources = hm_data_sources();
    if (!sources || !sources.length) {
        sources = hm_data_sources_background();
    }
    for (var index in sources) {
        source = sources[index];
        if (source.type == 'imap') {
            get_imap_folder_status(source.id, source.folder);
        }
    }
};

if (hm_list_path() == 'sent') {
    Hm_Message_List.page_caches.sent = 'formatted_sent_data';
}

$(function() {
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

    if ($('.imap_move').length > 0) {
        check_select_for_imap();
        $('.toggle_link').on("click", function() { setTimeout(search_selected_for_imap, 100); });
        Hm_Ajax.add_callback_hook('ajax_imap_folder_display', check_select_for_imap);
        Hm_Message_List.callbacks.push(check_select_for_imap);
        $('.imap_move').on("click", function() { return false; });
    }

    if (hm_list_path() !== 'unread') {
        if (typeof hm_data_sources_background === 'function') {
            globals.Hm_Background_Unread = new Message_List();
            globals.Hm_Background_Unread.background = true;
            globals.Hm_Background_Unread.add_sources(hm_data_sources_background());
            var interval = Hm_Utils.get_from_global('imap_background_update_interval', 33);
            Hm_Timer.add_job(globals.Hm_Background_Unread.load_sources, interval, true);
        }
    }
    var prefetch_interval = Hm_Utils.get_from_global('imap_prefetch_msg_interval', 43);
    Hm_Timer.add_job(imap_prefetch_msgs, prefetch_interval, true);
    setTimeout(prefetch_imap_folders, 2);
});


var imap_archive_message = function(state, supplied_uid, supplied_detail) {
    var uid = hm_msg_uid();
    var detail = Hm_Utils.parse_folder_path(hm_list_path(), 'imap');
    if (supplied_uid) {
        uid = supplied_uid;
    }
    if (supplied_detail) {
        detail = supplied_detail;
    }
    if (detail && uid) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_imap_archive_message'},
            {'name': 'imap_msg_uid', 'value': uid},
            {'name': 'imap_server_id', 'value': detail.server_id},
            {'name': 'folder', 'value': detail.folder}],
            function(res) {
                if (!res.imap_archive_error) {
                    if (Hm_Utils.get_from_global('msg_uid', false)) {
                        return;
                    }
                    var nlink = $('.nlink');
                    if (nlink.length) {
                        window.location.href = nlink.attr('href');
                    }
                    else {
                        if (!hm_list_parent()) {
                            window.location.href = "?page=message_list&list_path="+hm_list_path();
                        }
                        else {
                            window.location.href = "?page=message_list&list_path="+hm_list_parent();
                        }
                    }
                }
            }
        );
    }
    return false;
};
