$(function() {
    if (hm_page_name() == 'search') {
        if (hm_run_search === '1') {
            Hm_Message_List.load_sources();
        }
        $('.refresh_link').click(function() { return Hm_Message_List.load_sources(); });
    }
    $('.search_terms').val(hm_search_terms);
});
