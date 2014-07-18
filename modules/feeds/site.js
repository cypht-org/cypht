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

var add_feed_sources = function() {
    if ($('.feed_server_ids').length) {
        var ids = $('.feed_server_ids').val().split(',');
        if (ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Message_List.sources.push({type: 'feed', id: id, callback: feeds_combined_inbox_content});
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
        add_feed_sources();
    }
    else if (hm_list_path.substring(0, 4) == 'feed') {
        if ($('.message_table tbody tr').length == 0) {
            var detail = parse_folder_path(hm_list_path, 'feeds');
            if (detail) {
                Hm_Message_List.sources.push({type: 'feed', id: detail.server_id, callback: load_feed_list});
            }
            Hm_Message_List.load_sources();
        }
    }
}
else if (hm_page_name == 'message' && hm_list_path.substr(0, 4) == 'feed') {
    feed_item_view();
}
else if (hm_page_name == 'home') {
    feed_status_update();
}
