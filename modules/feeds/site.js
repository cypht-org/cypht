var feeds_combined_inbox = function() {
    var ids = $('.feeds_server_ids').val().split(',');
    if (ids && ids != '') {
        for (i=0;i<ids.length;i++) {
            id=ids[i];
            Hm_Ajax.request(
                [{'name': 'hm_ajax_hook', 'value': 'ajax_feed_combined_inbox'},
                {'name': 'feed_server_ids', 'value': id}],
                display_feeds_combined_inbox,
                [],
                false,
                set_combined_inbox_state
            );
        }
    }
    return false;
};
var display_feeds_combined_inbox = function(res) {
    var ids = res.combined_inbox_feed_server_ids.split(',');
    var count = Hm_Message_List.update(ids, res.formatted_feed_data, 'feeds');
};
