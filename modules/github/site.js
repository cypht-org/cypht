'use strict';

var github_item_view = function(list_path, uid, callback) {
    if (!list_path) {
        list_path = getListPathParam();
    }
    if (!uid) {
        uid = getMessageUidParam();
    }
    $('.msg_text_inner').html('');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_github_event_detail'},
        {'name': 'list_path', 'value': list_path},
        {'name': 'github_uid', 'value': uid}],
        display_github_item_content,
        [],
        false,
        callback
    );
    return false;
};

var display_github_item_content = function(res) {
    $('.msg_text').html(res.github_msg_text);
    var uid = getMessageUidParam();
    if (hm_list_parent() == 'unread') {
        Hm_Message_List.prev_next_links('formatted_unread_data', uid);
    }
    else if (hm_list_parent() == 'github_all') {
        Hm_Message_List.prev_next_links('formatted_github_all', uid);
    }
    else {
        Hm_Message_List.prev_next_links(getListPathParam(), uid);
    }
    if (Hm_Message_List.track_read_messages(uid)) {
        if (hm_list_parent() == 'unread') {
            Hm_Message_List.adjust_unread_total(-1);
        }
    }
    fixLtrInRtl();
};

var github_repo_update = function() {
    var repo;
    var repos = [];
    var i;
    $('.github_repo').filter(function() { repos.push($(this).data('id')); });
    for (i=0;i<repos.length;i++) {
        repo = repos[i];
        Hm_Ajax.request(
            [{'name': 'hm_ajax_hook', 'value': 'ajax_github_status'},
            {'name': 'github_repo', 'value': repo}],
            update_github_status_display
        );
    }
};

var update_github_status_display = function(res) {
    $('.github_'+Hm_Utils.clean_selector(res.github_status_repo)).html(res.github_status_display);
};
