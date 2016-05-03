'use strict'
var record_message = function(res) {
    var history = Hm_Utils.json_decode(Hm_Utils.get_from_local_storage('message_history'));
    if (!history) {
        history = {};
    }
    var dt = new Date();
    var dt_str = dt.getFullYear() + '/';
    dt_str += ('0'+(dt.getMonth()+1)).substr(-2) + '/';
    dt_str += ('0'+dt.getDate()).substr(-2) + ' ';
    dt_str += ('0'+dt.getHours()).substr(-2) + ':';
    dt_str += ('0'+dt.getMinutes()).substr(-2) + ':';
    dt_str += ('0'+dt.getSeconds()).substr(-2);

    history[window.location.href] = [
        $('th', $('.header_subject')).html(),
        $('td', $('.header_from')).html(),
        dt_str,
        $('a', $('.content_title')).html()
    ];
    Hm_Utils.save_to_local_storage('message_history', Hm_Utils.json_encode(history));
};

var display_history_page_links = function() {
    var msg;
    var history = Hm_Utils.json_decode(Hm_Utils.get_from_local_storage('message_history'));
    var count = 0;
    for (msg in history) {
        count++;
        if (history[msg][0] == null) {
            continue;
        }
        if (history[msg][1] == null) {
            history[msg][1] = '';
        }
        if (history[msg][2] == null) {
            history[msg][2] = '';
        }
        $('.message_table tbody').append('<tr><td class="source">'+history[msg][3]+'</td><td class="from">'+history[msg][1]+'</td><td class="subject"><div><a href="'+msg+'">'+history[msg][0]+'</a></div></td><td class="msg_date">'+history[msg][2]+'</td></tr>');
    }
    if (count === 0) {
        $('.history_content').append('<div class="empty_list">'+hm_empty_folder()+'</div>');
    }
};

$(function() {
    if (hm_page_name() == 'message') {
        Hm_Ajax.add_callback_hook('ajax_imap_message_content', record_message);
        Hm_Ajax.add_callback_hook('ajax_imap_mark_as_read', record_message);
        Hm_Ajax.add_callback_hook('ajax_feed_item_content', record_message);
        Hm_Ajax.add_callback_hook('ajax_pop3_message_display', record_message);
        Hm_Ajax.add_callback_hook('ajax_github_event_detail', record_message);
        Hm_Ajax.add_callback_hook('ajax_wp_notice_display', record_message);
    }
    else if (hm_page_name() == 'history') {
        display_history_page_links();
    }
});
