$(document).ajaxSuccess(function(event, xhr, settings) {
    var response = jQuery.parseJSON(xhr.responseText);
    if (typeof res == 'string' && (res == 'null' || res.indexOf('<') == 0)) {
        return;
    }
    if (response.module_debug) {
        $(".module_list").html(response.module_debug);
    }
    if (response.hm3_debug) {
        $(".hm3_debug").html(response.hm3_debug);
    }
    if (response.imap_summary_debug) {
        $(".hm3_imap_debug").html(response.imap_summary_debug);
    }
    if (response.pop3_summary_debug) {
        $(".hm3_pop3_debug").html(response.pop3_summary_debug);
    }
});
