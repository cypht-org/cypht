'use strict';

var hm_show_field_error = function(fieldId, message) {
    var $field = $('#' + fieldId);
    $field.addClass('is-invalid');
    if ($field.next('.invalid-feedback').length === 0) {
        $field.after('<div class="invalid-feedback">' + message + '</div>');
    }
};

var hm_clear_form_errors = function(formSelector) {
    $(formSelector + ' .is-invalid').removeClass('is-invalid');
    $(formSelector + ' .invalid-feedback').remove();
};
