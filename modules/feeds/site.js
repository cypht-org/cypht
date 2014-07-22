var feeds_combined_unread_content= function(id) {
    var since = 'today';
    if ($('.message_list_since').length) {
        since = $('.message_list_since option:selected').val();
    }
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_unread'},
        {'name': 'unread_since', 'value': since},
        {'name': 'feed_server_ids', 'value': id}],
        display_feeds_combined_unread,
        [],
        false,
        set_combined_feeds_unread_state
    );
    return false;
};

var set_combined_feeds_unread_state = function() {
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage('formatted_feed_data', data.html());
    $(':checkbox').click(function() {
        Hm_Message_List.toggle_msg_controls();
    });
};

var display_feeds_combined_unread = function(res) {
    var ids = res.feed_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_feed_data, 'feeds');
};

var feeds_combined_inbox_content= function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined_inbox'},
        {'name': 'limit', 'value': 10},
        {'name': 'feed_server_ids', 'value': id}],
        display_feeds_combined_inbox,
        [],
        false,
        set_combined_inbox_state
    );
    return false;
};

var display_feeds_combined_inbox = function(res) {
    var ids = res.feed_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_feed_data, 'feeds');
};

var add_feed_sources = function(callback) {
    if ($('.feed_server_ids').length) {
        var ids = $('.feed_server_ids').val().split(',');
        if (ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Message_List.sources.push({type: 'feed', id: id, callback: callback});
            }
        }
    }
};

var feed_item_view = function() {
    $('.msg_text_inner').html('');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_item_content'},
        {'name': 'feed_list_path', 'value': hm_list_path},
        {'name': 'feed_uid', 'value': hm_msg_uid}],
        display_feed_item_content,
        [],
        false
    );
    return false;
};

var display_feed_item_content = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.feed_msg_headers);
    $('.msg_text').append(res.feed_msg_text);
    set_message_content();
    document.title = 'HM3 '+$('.header_subject th').text();
};

var load_feed_list = function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_list_display'},
        {'name': 'limit', 'value': 40},
        {'name': 'feed_server_ids', 'value': detail.server_id}],
        display_feed_list,
        [],
        false
    );
    return false;
};

var display_feed_list = function(res) {
    ids = [res.feed_server_ids];
    var count = Hm_Message_List.update(ids, res.formatted_feed_data, 'feeds');
    key = 'feeds_'+res.feed_server_ids;
    var data = $('.message_table tbody');
    data.find('*[style]').attr('style', '');
    save_to_local_storage(key, data.html());
};

var feed_status_update = function() {
    if ($('.feed_server_ids').length) {
        var ids = $('.feed_server_ids').val().split(',');
        if ( ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Ajax.request(
                    [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_status'},
                    {'name': 'feed_server_ids', 'value': id}],
                    update_feed_status_display,
                    [],
                    false
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

if (hm_page_name == 'message_list') {
    if (hm_list_path == 'combined_inbox') {
        add_feed_sources(feeds_combined_inbox_content);
    }
    else if (hm_list_path == 'feeds') {
        add_feed_sources(feeds_combined_unread_content);
    }
    else if (hm_list_path.substring(0, 4) == 'feed') {
        if ($('.message_table tbody tr').length == 0) {
            var detail = parse_folder_path(hm_list_path, 'feeds');
            if (detail) {
                Hm_Message_List.sources.push({type: 'feed', id: detail.server_id, callback: load_feed_list});
            }
            Hm_Message_List.setup_combined_view(hm_list_path);
        }
    }
}
else if (hm_page_name == 'message' && hm_list_path.substr(0, 4) == 'feed') {
    feed_item_view();
}
else if (hm_page_name == 'home') {
    feed_status_update();
}
