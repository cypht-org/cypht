'use strict';
$(function() {
    if (hm_page_name() == 'calendar') {
        $('.event_delete').click(function() {
            $(this).parent().submit();
        });
        $('.cal_title').click(function() {
            $('.event_details').hide();
            $('.event_details', $(this).parent()).show();
            $('.event_details').click(function() {
                $(this).hide();
            });
        });
    }
});
