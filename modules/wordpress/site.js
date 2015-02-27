var load_freshly_pressed = function() {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_wordpess_freshly_pressed'}],
        display_wordpress_freshly_pressed,
        [],
        false
    );
};

var display_wordpress_freshly_pressed = function(res) {
    Hm_Message_List.update([0], res.formatted_message_list, 'wp_freshly_pressed');
    Hm_Message_List.set_message_list_state('formatted_wp_freshly_pressed')
};

var load_wp_notices_for_combined_list = function() {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_wordpess_notifications'}],
        display_combined_wp_notices,
        [],
        false
    );
};

var load_wp_notices = function() {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_wordpess_notifications'}],
        display_wordpress_notices,
        [],
        false
    );
};

var display_combined_wp_notices = function(res) {
    Hm_Message_List.update([0], res.formatted_message_list, 'wp_notices');
};

var display_wordpress_notices = function(res) {
    Hm_Message_List.update([0], res.formatted_message_list, 'wp_notices');
    Hm_Message_List.set_message_list_state('formatted_wp_notice_data')
};

var wp_notice_view = function() {
    $('.msg_text_inner').html('');
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_wp_notice_display'}],
        display_wp_notice,
        [],
        false
    );
    return false;
};

var display_wp_notice = function(res) {
    $('.msg_text').html('');
    $('.msg_text').append(res.wp_notice_headers);
    $('.msg_text').append(res.wp_notice_text);
};

if (hm_page_name() == 'message_list' && hm_list_path() == 'wp_notifications') {
    Hm_Message_List.page_caches.wp_notifications = 'formatted_wp_notice_data';
    Hm_Message_List.select_combined_view();
}
else if (hm_page_name() == 'message_list' && hm_list_path() == 'wp_freshly_pressed') {
    Hm_Message_List.page_caches.wp_freshly_pressed = 'formatted_wp_freshly_pressed';
    Hm_Message_List.select_combined_view();
}
else if (hm_page_name() == 'message' && hm_list_path() == 'wp_notifications') {
    wp_notice_view();
}
