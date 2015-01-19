var load_hacker_news = function() {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_hacker_news_data'}],
        display_hacker_news,
        [],
        false
    );
};
var display_hacker_news = function(res) {
    Hm_Message_List.update([0], res.formatted_message_list, 'hacker_news');
}

if (hm_page_name() == 'hacker_news') {
    load_hacker_news();
}
