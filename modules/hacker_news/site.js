var load_hacker_news = function() {
    Hm_Ajax.request([{'name': 'hm_ajax_hook', 'value': 'ajax_hacker_news_data'}], display_hacker_news);
};

var display_hacker_news = function(res) {
    Hm_Message_List.update([0], res.formatted_message_list, 'hacker_news');
    if (hm_list_path() == 'hn_trending') {
        Hm_Message_List.set_message_list_state('formatted_hn_trending')
    }
    else if (hm_list_path() == 'hn_newest') {
        Hm_Message_List.set_message_list_state('formatted_hn_newest')
    }
};

if (hm_page_name() == 'message_list') {
    if (hm_list_path() == 'hn_newest') {
        Hm_Message_List.page_caches.hn_newest = 'formatted_hn_newest';
    }
    else if (hm_list_path() == 'hn_trending') {
        Hm_Message_List.page_caches.hn_trending = 'formatted_hn_trending';
    }
}
