'use strict';

var load_wp_notices_for_combined_list = function() {
    Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_wordpess_notifications'}], display_combined_wp_notices);
};

var load_wp_notices = function() {
    Hm_Ajax.request( [{'name': 'hm_ajax_hook', 'value': 'ajax_wordpess_notifications'}], display_wordpress_notices);
};

var display_combined_wp_notices = function(res) {
    Hm_Message_List.update(res.formatted_message_list);
};

var display_wordpress_notices = function(res) {
    Hm_Message_List.update(res.formatted_message_list);
    Hm_Message_List.set_message_list_state('formatted_wp_notice_data')
};

var wp_notice_view = function(uid, callback) {
    if (!uid) {
        uid = getMessageUidParam();
    }
    $('.msg_text_inner').html('');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_wp_notice_display'},
        {'name': 'wp_uid', 'value': uid}],
        display_wp_notice,
        [],
        false,
        callback
    );
    return false;
};

var display_wp_notice = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.wp_notice_headers);
    $('.msg_text').append(res.wp_notice_text);
    var path = getListPathParam();
    var uid = getMessageUidParam();
    if (hm_list_parent() == 'unread') {
        Hm_Message_List.prev_next_links('formatted_unread_data', uid);
    }
    else if (path == 'wp_notifications') {
        Hm_Message_List.prev_next_links('formatted_wp_notice_data', uid);
    }
    Hm_Message_List.track_read_messages(path+'_'+uid);

};

function wpMessageListPageHandler(routeParams) {
    if (routeParams.list_path == 'wp_notifications') {
        Hm_Message_List.page_caches.wp_notifications = 'formatted_wp_notice_data';
    }
}

function wpServersPageHandler() {
    if ($('#wp_disconnect_form').length) {
        $('#wp_disconnect_form').submit(function(e) {
            if (!hm_delete_prompt()) {
                e.preventDefault();
                return false;
            }
            return true;
        });
    }
}

function wpMessageContentPageHandler(routeParams) {
    if (routeParams.list_path == 'wp_notifications') {
        wp_notice_view();
    }
}
