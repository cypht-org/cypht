var load_wp_notices = function() {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_wordpess_notifications'}],
        display_wordpress_notices,
        [],
        false
    );
};
var display_wordpress_notices = function(res) {
    Hm_Message_List.update([0], res.formatted_message_list, 'wp_notices');
}

if (hm_page_name() == 'wordpress_notifications') {
    load_wp_notices();
}
