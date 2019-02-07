'use strict';

/**
 * This JS sets up an AJAX request and assigns it to a link on the hello_world page.
 * You have access to cash.js functions when this code is loaded, so use the standard
 * way to delay actions until page onload if you need to. Built in data sources like
 * hm_page_name() are defined before this is run so they are also available. When the
 * site build process is run this code will be combined with JS from other module sets,
 * and optionally minified based on the hm3.ini file settings.
 */

/**
 * Called when a user clicks on the "AJAX Example" link
 */
var hello_world_ajax_update = function() {
    Hm_Ajax.request(
        [{'name': 'hm_ajax_hook', 'value': 'ajax_hello_world'}],
        update_hello_world_display
    );
};

/**
 * Callback for hello_world_ajax_update
 */
var update_hello_world_display = function(res) {
    alert(res.hello_world_ajax_result);
};

/**
 * If we are on the "hello_world" page, activate the click handler
 */
if (hm_page_name() == 'hello_world') {
    $('.hw_ajax_link').on("click", function() {
        hello_world_ajax_update();
    });
}
