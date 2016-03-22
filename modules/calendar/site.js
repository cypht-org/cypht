$(function() {
    if (hm_page_name() == 'calendar') {
        $('.cal_title').click(function() {
            $('.event_details').hide();
            $('.event_details', $(this).parent()).show();
            $('.event_details').click(function() {
                $(this).hide();
            });
        });
    }
});
