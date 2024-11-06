'use strict';

var load_github_data = function(id) {
    if (getListPathParam() == 'github_all') {
        Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_github_data'}, {'name': 'github_repo', 'value': id}], display_github_data, [], false, cache_github_all);
    }
    else {
        if (getListPathParam().substr(0, 6) == 'github') {
            var cached = Hm_Utils.get_from_local_storage(getListPathParam());
            if (cached) {
                $('.message_table tbody').html(cached);
            }
        }
        Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_github_data'}, {'name': 'github_repo', 'value': id}], display_github_data);
    }
};

var load_github_data_background = function(id) {
        Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_github_data'}, {'name': 'github_unread', 'value': 1}, {'name': 'github_repo', 'value': id}], display_github_data_background);
};

var display_github_data_background = function(res) {
    var ids = [res.github_server_id];
    var cache = $('<tbody></tbody>').append($(Hm_Utils.get_from_local_storage('formatted_unread_data')));
    globals.Hm_Background_Unread.update(ids, res.formatted_message_list, 'github', cache);
    Hm_Utils.save_to_local_storage('formatted_unread_data', cache.html());
};

var display_github_data = function(res) {
    var path = getListPathParam();
    Hm_Message_List.update(res.formatted_message_list);
    if (path != 'github_all') {
        var data = Hm_Message_List.filter_list();
        data.find('*[style]').attr('style', '');
        Hm_Utils.save_to_local_storage(path, data.html());
    }
};

var cache_github_all = function() {
    if (getListPathParam() == 'github_all') {
        Hm_Message_List.set_message_list_state('formatted_github_all')
    }
};

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

function githubServersPageHandler() {
    var dsp = Hm_Utils.get_from_local_storage('.github_connect_section');
    if (dsp == 'block' || dsp == 'none') {
        $('.github_connect_section').css('display', dsp);
    }
    $('.github_disconnect').on("click", function(e) {
        if (!hm_delete_prompt()) {
            e.preventDefault();
            return false;
        }
        return true;
    });
    $('.github_remove_repo').on("click", function(e) {
        if (!hm_delete_prompt()) {
            e.preventDefault();
            return false;
        }
        return true;
    });
}

function githubMessageContentPageHandler(routeParams) {
    if (routeParams.list_path.substr(0, 6) == 'github') {
        github_item_view();
    }
}

function githubMessageListPageHandler(routeParams) {
    if (routeParams.list_path === 'github_all') {
        Hm_Message_List.page_caches.github_all = 'formatted_github_all';
    }
}
