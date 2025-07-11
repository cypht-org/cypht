'use strict';

/**
 * This JS sets up an AJAX request and assigns it to a link on the hello_world page.
 * You have access to cash.js functions when this code is loaded, so use the standard
 * way to delay actions until page onload if you need to. When the
 * site build process is run this code will be combined with JS from other module sets,
 * and optionally minified based on the config/app.php file settings.
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