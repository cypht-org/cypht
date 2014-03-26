var tracker_update_count = 0;

$(document).ajaxSuccess(function(event, xhr, settings) {
    var response = jQuery.parseJSON(xhr.responseText);
    if (typeof res == 'string' && (res == 'null' || res.indexOf('<') == 0)) {
        return;
    }
    tracker_update_count++;
    if (tracker_update_count == 10) {
        $(".module_list").html('');
        $(".hm3_debug").html('');
        $(".hm3_imap_debug").html('');
        $(".hm3_pop3_debug").html('');
        tracker_update_count = 0;
    }
    console.log(tracker_update_count);
    if (response.module_debug) {
        $(".module_list").prepend(response.module_debug);
    }
    if (response.hm3_debug) {
        $(".hm3_debug").prepend(response.hm3_debug);
    }
    if (response.imap_summary_debug) {
        $(".hm3_imap_debug").prepend(response.imap_summary_debug);
    }
    if (response.pop3_summary_debug) {
        $(".hm3_pop3_debug").prepend(response.pop3_summary_debug);
    }
});
