'use strict';

var feed_test_action = function(event) {
    event.preventDefault();
    Hm_Notices.hide(true);
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function() {},
        {'feed_connect': 1}
    );
};

var feed_delete_action = function(event) {
    if (!hm_delete_prompt()) {
        return false;
    }
    event.preventDefault();
    Hm_Notices.hide(true);
    var form = $(this).parent();
    Hm_Ajax.request(
        form.serializeArray(),
        function(res) {
            if (res.deleted_server_id) {
                Hm_Utils.set_unsaved_changes(1);
                Hm_Folders.reload_folders(true);
                form.parent().parent().remove();
                decrease_servers('feed');
            }
        },
        {'delete_feed': 1}
    );
};

var feeds_search_page_content = function(id) {
    if (hm_search_terms) {
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined'},
            {'name': 'feed_search', 'value': 1},
            {'name': 'feed_server_ids', 'value': id}],
            display_feeds_search_result,
            [],
            false,
            Hm_Message_List.set_search_state
        );
    }
    return false;
};

var display_feeds_search_result = function(res) {
    Hm_Message_List.update(res.formatted_message_list);
};

var feeds_combined_content_unread = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined'},
        {'name': 'feed_unread_only', 'value': 1},
        {'name': 'feed_server_ids', 'value': id}],
        display_feeds_combined_unread,
        [],
        false,
        Hm_Message_List.set_unread_state
    );
    return false;
};

var display_feeds_combined_unread = function(res) {
    Hm_Message_List.update(res.formatted_message_list);
};

var feeds_combined_content = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined'},
        {'name': 'feed_server_ids', 'value': id}],
        display_feeds_combined,
        [],
        false,
        set_combined_feeds_state
    );
    return false;
};

var set_combined_feeds_state = function() {
    var data = Hm_Message_List.filter_list();
    data.find('*[style]').attr('style', '');
    Hm_Utils.save_to_local_storage('formatted_feed_data', data.html());
    $('input[type=checkbox]').on("click", function() {
        Hm_Message_List.toggle_msg_controls();
    });
    Hm_Message_List.update_title();
};

var display_feeds_combined = function(res) {
    Hm_Message_List.update(res.formatted_message_list);
    $('.total').text($('.message_table tbody tr').length);
};

var feeds_combined_inbox_content= function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined'},
        {'name': 'feed_server_ids', 'value': id}],
        display_feeds_combined_inbox,
        [],
        false,
        Hm_Message_List.set_combined_inbox_state
    );
    return false;
};

var display_feeds_combined_inbox = function(res) {
    Hm_Message_List.update(res.formatted_message_list);
};

var feed_item_view = function(uid, list_path, callback) {
    if (!uid) {
        uid = getMessageUidParam();
    }
    if (!list_path) {
        list_path = getListPathParam();
    }
    $('.msg_text_inner').html('');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_item_content'},
        {'name': 'feed_list_path', 'value': list_path},
        {'name': 'feed_uid', 'value': uid}],
        display_feed_item_content,
        [],
        false,
        callback
    );
    return false;
};

var display_feed_item_content = function(res) {
    if (!res.feed_msg_headers) {
        return;
    }
    var msg_uid = getMessageUidParam();
    $('.msg_text').html('');
    $('.msg_text').append(res.feed_msg_headers);
    $('.msg_text').append(res.feed_msg_text);
    set_message_content();
    document.title = $('.header_subject th').text();
    var path = getListPathParam();
    if (hm_list_parent() == 'feeds') {
        Hm_Message_List.prev_next_links('formatted_feed_data', path+'_'+msg_uid);
    }
    else if (hm_list_parent() == 'combined_inbox') {
        Hm_Message_List.prev_next_links('formatted_combined_inbox', path+'_'+msg_uid);
    }
    else if (hm_list_parent() == 'unread') {
        Hm_Message_List.prev_next_links('formatted_unread_data', path+'_'+msg_uid);
    }
    else if (hm_list_parent() === 'search') {
        Hm_Message_List.prev_next_links('formatted_search_data', path+'_'+msg_uid);
    }
    else {
        Hm_Message_List.prev_next_links(path, path+'_'+msg_uid);
    }
    if (Hm_Message_List.track_read_messages(path+'_'+msg_uid)) {
        if (hm_list_parent() == 'unread') {
            Hm_Message_List.adjust_unread_total(-1);
        }
    }
    fixLtrInRtl();
};

var load_feed_list = function(id) {
    var cached = Hm_Utils.get_from_local_storage(getListPathParam());
    if (cached) {
        $('.message_table tbody').html(cached);
    }
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined'},
        {'name': 'feed_server_ids', 'value': id}],
        display_feed_list
    );
    return false;
};

var display_feed_list = function(res) {
    var ids = [res.feed_server_ids];
    Hm_Message_List.update(res.formatted_message_list);
    var key = 'feeds_'+res.feed_server_ids;
    var data = Hm_Message_List.filter_list();
    data.find('*[style]').attr('style', '');
    $('.total').text($('.message_table tbody tr').length);
    Hm_Utils.save_to_local_storage(key, data.html());
};

var feed_status_update = function() {
    var id;
    var i;
    if ($('.feed_server_ids').length) {
        var ids = $('.feed_server_ids').val().split(',');
        if ( ids && ids !== '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Ajax.request(
                    [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_status'},
                    {'name': 'feed_server_ids', 'value': id}],
                    update_feed_status_display
                );
            }
        }
    }
    return false;
};

var update_feed_status_display = function(res) {
    var id = res.feed_status_server_id;
    $('.feeds_status_'+id).html(res.feed_status_display);
};

var expand_feed_settings = function() {
    var hash = window.location.hash;
    if (hash) {
        if (hash.replace('#', '.') == '.feeds_setting') {
            $('.feeds_setting').css('display', 'table-row');
        }
    }
    else {
        var dsp = Hm_Utils.get_from_local_storage('.feeds_setting');
        if (dsp == 'table-row' || dsp == 'none') {
            $('.feeds_setting').css('display', dsp);
        }
    }
};

function feedMessageContentPageHandler(routeParams) {
    if (routeParams.list_path.substr(0, 4) == 'feed') {
        feed_item_view();
    }
}

function feedServersPageHandler() {
    $('.feed_delete').on('click', feed_delete_action);
    $('.test_feed_connect').on('click', feed_test_action);
    var dsp = Hm_Utils.get_from_local_storage('.feed_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.feed_section').css('display', dsp);
    }
}
