'use strict';

/**
 * Hides a Bootstrap modal by its element id.
 * @param {string} modalId - The id attribute of the modal element (without #)
 */
var hm_hide_modal = function(modalId) {
    var modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
        modal.hide();
    }
};
