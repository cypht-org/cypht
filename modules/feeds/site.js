var feeds_combined_inbox_content= function(id) {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined_inbox'},
        {'name': 'feed_server_ids', 'value': id}],
        display_feeds_combined_inbox,
        [],
        false,
        set_combined_inbox_state
    );
    return false;
};

var display_feeds_combined_inbox = function(res) {
    var ids = res.combined_inbox_feed_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_feed_data, 'feeds');
};

var add_feed_sources = function() {
    if ($('.feeds_server_ids').length) {
        var ids = $('.feeds_server_ids').val().split(',');
        if (ids && ids != '') {
            for (i=0;i<ids.length;i++) {
                id=ids[i];
                Hm_Message_List.sources.push({type: 'feed', id: id, callback: feeds_combined_inbox_content});
            }
        }
    }
};

var display_feed_item_content = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.feed_msg_headers);
    $('.msg_text').append(res.feed_msg_text);
    set_message_content();
    document.title = 'HM3 '+$('.header_subject th').text();
};
var feed_view = function() {
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

if (hm_page_name == 'message_list') {
    if (hm_list_path == 'combined_inbox') {
        add_feed_sources();
    }
}
else if (hm_page_name == 'message') {
    feed_view();
}
