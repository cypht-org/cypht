'use strict';

/**
 * Returns true if an Hm_Ajax response contains at least one success message.
 * @param {Object} res - The AJAX response object
 */
var hm_ajax_has_success = function(res) {
    if (res.router_user_msgs) {
        for (var key in res.router_user_msgs) {
            if (res.router_user_msgs[key].type === 'success') {
                return true;
            }
        }
    }
    return false;
};
