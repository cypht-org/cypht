$(function() {
    if (hm_page_name() == 'search') {
        Hm_Message_List.load_sources();
    }
    $('.search_terms').val(hm_search_terms);
});
