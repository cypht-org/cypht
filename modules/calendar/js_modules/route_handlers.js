function applyCalendarPageHandlers() {
    $('.event_delete').on("click", function() {
        if (hm_delete_prompt()) {
            $(this).parent().submit();
        }
    });
    $('.cal_title').on("click", function(e) {
        e.preventDefault();
        $('.event_details').hide();
        $('.event_details', $(this).parent()).show();
        $('.event_details').on("click", function() {
            $(this).hide();
        });
    });
}