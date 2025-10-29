function applyCalendarPageHandlers() {
    $('.event_delete a').on("click", function() {
        if (hm_delete_prompt()) {
            $(this).closest('form').submit();
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