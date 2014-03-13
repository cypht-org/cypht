$(document).ajaxSuccess(function(event, xhr, settings) {
    var debug_data = jQuery.parseJSON(xhr.responseText); $(".module_list").html(debug_data.module_debug);
});
