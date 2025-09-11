'use strict';

$(function() {
    $('.ldap_password_change').on("click", function() {
        $(this).prev().prop('disabled', false);
        $(this).prev().attr('placeholder', '');
    });

    function toggleUsernameField() {
        var uidattr = $('#ldap_uidattr').val();
        var usernameFieldWrapper = $('#ldap_uid_field_wrapper');
        var usernameField = $('#ldap_uid');
        
        if (uidattr === 'uid') {
            usernameFieldWrapper.show();
            usernameField.prop('required', true);
        } else {
            usernameFieldWrapper.hide();
            usernameField.prop('required', false);
            usernameField.val('');
        }
    }

    toggleUsernameField();
    $('#ldap_uidattr').on('change', toggleUsernameField);
});
