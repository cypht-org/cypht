'use strict';

/**
 * Marks a form field as invalid and injects an error message below it.
 * @param {string} fieldId - The id attribute of the input element (without #)
 * @param {string} message - The validation error message to display
 */
var hm_show_field_error = function(fieldId, message) {
    var $field = $('#' + fieldId);
    $field.addClass('is-invalid');
    if ($field.next('.invalid-feedback').length === 0) {
        $field.after('<div class="invalid-feedback">' + message + '</div>');
    }
};

/**
 * Removes all validation error states and messages from a form.
 * @param {string} formSelector - CSS selector for the form (e.g. '#my-form')
 */
var hm_clear_form_errors = function(formSelector) {
    $(formSelector + ' .is-invalid').removeClass('is-invalid');
    $(formSelector + ' .invalid-feedback').remove();
};
